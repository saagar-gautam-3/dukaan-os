<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateSale
{
    /**
     * Create a draft sale with items.
     *
     * @param  array{
     *     warehouse_id?: int|null,
     *     customer_id?: int|null,
     *     notes?: string|null,
     *     items: array<array{
     *         product_id: int,
     *         product_variant_id?: int|null,
     *         quantity: string,
     *         unit_price: string,
     *         discount?: string,
     *         tax?: string,
     *     }>,
     * } $data
     */
    public function handle(array $data): Sale
    {
        if (empty($data['items'])) {
            throw new InvalidArgumentException('A sale must have at least one item.');
        }

        $shop = TenantContext::shop();

        return DB::transaction(function () use ($data, $shop) {
            // Calculate totals via bcmath — never trust client totals
            $subtotal = '0.00';
            $discountTotal = '0.00';
            $taxTotal = '0.00';

            $itemsToCreate = [];

            foreach ($data['items'] as $item) {
                $qty = (string) ($item['quantity'] ?? '0');
                $price = (string) ($item['unit_price'] ?? '0');
                $discount = (string) ($item['discount'] ?? '0');
                $tax = (string) ($item['tax'] ?? '0');

                // line_total = (unit_price - discount + tax) * quantity
                $linePrice = bcadd(bcsub($price, $discount, 4), $tax, 4);
                $lineTotal = bcmul($linePrice, $qty, 2);

                $subtotal = bcadd($subtotal, bcmul($price, $qty, 2), 2);
                $discountTotal = bcadd($discountTotal, bcmul($discount, $qty, 2), 2);
                $taxTotal = bcadd($taxTotal, bcmul($tax, $qty, 2), 2);

                $itemsToCreate[] = [
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $item['product_variant_id'] ?? null,
                    'quantity' => $qty,
                    'unit_price' => $price,
                    'unit_cost' => '0.00', // filled on PostSale from stock_balances
                    'discount' => $discount,
                    'tax' => $tax,
                    'line_total' => $lineTotal,
                    'shop_id' => $shop->id,
                ];
            }

            $grandTotal = bcadd(bcsub($subtotal, $discountTotal, 2), $taxTotal, 2);

            $sale = Sale::create([
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'customer_id' => $data['customer_id'] ?? null,
                'status' => SaleStatus::Draft,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => $taxTotal,
                'grand_total' => $grandTotal,
                'paid_total' => '0.00',
                'notes' => $data['notes'] ?? null,
            ]);

            $sale->items()->createMany($itemsToCreate);

            return $sale->load('items');
        });
    }
}
