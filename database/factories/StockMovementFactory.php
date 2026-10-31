<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockMovement>
 */
class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $warehouse = TenantContext::runAs($shop, fn () => Warehouse::factory()->create(['shop_id' => $shop->id]));
        $category = TenantContext::runAs($shop, fn () => Category::factory()->create(['shop_id' => $shop->id]));
        $unit = Unit::firstOrCreate(['symbol' => 'pc'], [
            'name' => 'Piece',
            'is_fractional' => false,
        ]);
        $product = TenantContext::runAs($shop, fn () => Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
        ]));

        return [
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'movement_type' => MovementType::Opening,
            'quantity' => 100.000,
            'unit_cost' => 50.00,
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Initial stock movement',
            'created_by' => User::factory(),
        ];
    }
}
