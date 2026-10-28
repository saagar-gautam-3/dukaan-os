<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopUser>
 */
class ShopUserFactory extends Factory
{
    protected $model = ShopUser::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory(),
            'role_id' => Role::factory(),
            'status' => 'active',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => 'inactive',
        ]);
    }
}
