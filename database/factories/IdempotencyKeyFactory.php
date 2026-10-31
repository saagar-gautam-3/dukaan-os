<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\IdempotencyKey;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IdempotencyKey>
 */
class IdempotencyKeyFactory extends Factory
{
    protected $model = IdempotencyKey::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory(),
            'key' => fake()->uuid(),
            'action' => 'post_sale',
            'result_id' => null,
            'result_type' => null,
        ];
    }
}
