<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\Customer;
use App\Models\HeldSale;
use App\Models\Product;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ResumeHeldSale
{
    public function handle(HeldSale $heldSale): array
    {
        $shop = TenantContext::shop();

        return DB::transaction(function () use ($heldSale, $shop): array {
            $locked = HeldSale::where('id', $heldSale->id)->lockForUpdate()->first();

            if (! $locked) {
                throw new InvalidArgumentException(__('errors.held_sale_not_found'));
            }

            $cartData = is_array($locked->cart) ? $locked->cart : [];
            $productIds = array_values(array_unique(array_map(
                static fn ($line) => (int) ($line['product_id'] ?? 0),
                $cartData
            )));

            $products = Product::where('shop_id', $shop->id)
                ->whereIn('id', $productIds)
                ->with(['stockBalances', 'unit'])
                ->get()
                ->keyBy('id');

            $items = [];
            $dropped = [];

            foreach ($cartData as $line) {
                $productId = (int) ($line['product_id'] ?? 0);
                $quantity = (string) ($line['quantity'] ?? '0');
                $product = $products->get($productId);

                if (! $product || ! $product->is_active) {
                    $dropped[] = $product?->name ?? ('#'.$productId);

                    continue;
                }

                $stock = $product->stockBalances->reduce(
                    static fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                    '0.000'
                );

                $items[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'name_ne' => $product->name_ne,
                    'unit' => $product->unit?->symbol,
                    'price' => (string) $product->selling_price,
                    'quantity' => $quantity,
                    'track_stock' => $product->track_stock,
                    'max_stock' => $stock,
                ];
            }

            $customer = null;
            if ($locked->customer_id !== null) {
                $c = Customer::where('shop_id', $shop->id)->find($locked->customer_id);
                if ($c) {
                    $customer = [
                        'id' => $c->id,
                        'name' => $c->name,
                        'phone' => $c->phone,
                        'credit_limit' => (string) $c->credit_limit,
                        'balance' => (string) $c->balance,
                    ];
                }
            }

            $locked->delete();

            return [
                'held_sale_id' => (int) $heldSale->id,
                'label' => $locked->label,
                'customer' => $customer,
                'items' => $items,
                'dropped' => $dropped,
            ];
        });
    }
}
