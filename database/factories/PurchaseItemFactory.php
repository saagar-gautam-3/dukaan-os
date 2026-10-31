<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $purchase = TenantContext::runAs($shop, fn () => Purchase::factory()->create(['shop_id' => $shop->id]));
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10.000,
            'unit_cost' => 100.00,
            'discount' => 0.00,
            'line_total' => 1000.00,
        ];
    }
}
