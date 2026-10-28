<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    protected $model = AuditLog::class;

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
            'event' => 'created',
            'auditable_type' => Shop::class,
            'auditable_id' => 1,
            'old_values' => null,
            'new_values' => ['name' => 'Demo Shop'],
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit Test',
        ];
    }
}
