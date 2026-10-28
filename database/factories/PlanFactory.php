<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Plan>
 */
class PlanFactory extends Factory
{
    protected $model = Plan::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Starter', 'Standard', 'Pro', 'Enterprise']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'limits' => [
                'max_users' => 5,
                'max_products' => 1000,
            ],
            'price' => fake()->randomElement([0.00, 999.00, 1999.00]),
            'is_active' => true,
        ];
    }
}
