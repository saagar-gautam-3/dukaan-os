<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Shop;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->company().' Suppliers',
            'phone' => fake()->numerify('98########'),
            'address' => fake()->address(),
            'pan_no' => fake()->numerify('30#######'),
            'is_active' => true,
        ];
    }
}
