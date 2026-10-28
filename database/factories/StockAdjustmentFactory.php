<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AdjustmentStatus;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<StockAdjustment>
 */
class StockAdjustmentFactory extends Factory
{
    protected $model = StockAdjustment::class;

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
            'adjustment_no' => fake()->unique()->lexify('ADJ-??????'),
            'reason' => 'Inventory Audit Discrepancy',
            'status' => AdjustmentStatus::Draft,
            'posted_at' => null,
            'created_by' => User::factory(),
        ];
    }
}
