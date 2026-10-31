<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use App\Models\Shop;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockBalance>
 */
class StockBalanceFactory extends Factory
{
    protected $model = StockBalance::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $warehouse = TenantContext::runAs($shop, fn () => Warehouse::factory()->create(['shop_id' => $shop->id]));
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'quantity' => fake()->randomFloat(3, 10, 200),
        ];
    }
}
