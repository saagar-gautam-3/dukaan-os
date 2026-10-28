<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Role>
 */
class RoleFactory extends Factory
{
    protected $model = Role::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->unique()->jobTitle(),
            'guard_name' => 'web',
        ];
    }
}
