<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Warehouse>
 */
class WarehouseFactory extends Factory
{
    protected $model = Warehouse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->city().' Godown',
            'code' => fake()->unique()->lexify('WH-???'),
            'address' => fake()->address(),
            'is_default' => false,
            'is_active' => true,
        ];
    }
}
