<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\Shop;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
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
        return [
            'shop_id' => Shop::factory(),
            'warehouse_id' => Warehouse::factory(),
            'product_id' => Product::factory(),
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
