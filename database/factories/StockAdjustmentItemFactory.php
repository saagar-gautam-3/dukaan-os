<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\Unit;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustmentItem>
 */
class StockAdjustmentItemFactory extends Factory
{
    protected $model = StockAdjustmentItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $warehouse = TenantContext::runAs($shop, fn () => Warehouse::factory()->create(['shop_id' => $shop->id]));
        $adjustment = TenantContext::runAs($shop, fn () => StockAdjustment::factory()->create(['shop_id' => $shop->id, 'warehouse_id' => $warehouse->id]));
        $category = TenantContext::runAs($shop, fn () => Category::factory()->create(['shop_id' => $shop->id]));
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], ['name' => 'Piece', 'is_fractional' => false]);
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create(['shop_id' => $shop->id, 'category_id' => $category->id, 'unit_id' => $unit->id]));

        return [
            'shop_id' => $shop->id,
            'adjustment_id' => $adjustment->id,
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => 10.000,
            'unit_cost' => 50.00,
        ];
    }
}
