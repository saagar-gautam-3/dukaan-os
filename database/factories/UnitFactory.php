<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Unit;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Unit>
 */
class UnitFactory extends Factory
{
    protected $model = Unit::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $symbol = fake()->unique()->lexify('u???');

        return [
            'name' => fake()->word(),
            'symbol' => $symbol,
            'is_fractional' => true,
        ];
    }
}
