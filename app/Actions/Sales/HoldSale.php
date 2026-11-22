<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Models\HeldSale;
use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class HoldSale
{
    public const MAX_HELD_BILLS = 20;

    public const MAX_LINES = 200;

    /**
     * @param  array{
     *     label?: string|null,
     *     customer_id?: int|null,
     *     items: array<array{product_id: int, quantity: string}>
     * } $data
     */
    public function handle(array $data): HeldSale
    {
        $shop = TenantContext::shop();

        $items = $data['items'] ?? [];
        if (empty($items) || count($items) > self::MAX_LINES) {
            throw new InvalidArgumentException(__('errors.held_sale_items_invalid'));
        }

        // Enforce per-shop limit.
        $existingCount = HeldSale::where('shop_id', $shop->id)->count();
        if ($existingCount >= self::MAX_HELD_BILLS) {
            throw new InvalidArgumentException(__('errors.held_sale_limit_reached'));
        }

        // Validate each line against real products of the shop.
        $productIds = array_map(static fn ($i) => (int) ($i['product_id'] ?? 0), $items);
        $products = Product::where('shop_id', $shop->id)
            ->whereIn('id', $productIds)
            ->get()
            ->keyBy('id');

        $cart = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $quantity = (string) ($item['quantity'] ?? '0');

            $product = $products->get($productId);
            if (! $product) {
                throw new InvalidArgumentException(__('errors.held_sale_product_invalid'));
            }

            $cart[] = [
                'product_id' => $productId,
                'quantity' => $quantity,
            ];
        }

        return DB::transaction(function () use ($data, $cart) {
            return HeldSale::create([
                'user_id' => (int) auth()->id(),
                'customer_id' => $data['customer_id'] ?? null,
                'label' => $data['label'] ?? null,
                'cart' => $cart,
            ]);
        });
    }
}
