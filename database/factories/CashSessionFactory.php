<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashSessionFactory extends Factory
{
    protected $model = CashSession::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'branch_id' => null,
            'opened_by' => User::factory(),
            'closed_by' => null,
            'opening_cash' => '5000.00',
            'closing_cash' => null,
            'expected_cash' => null,
            'difference' => null,
            'status' => CashSessionStatus::Open,
            'opened_at' => now(),
            'closed_at' => null,
            'notes' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => CashSessionStatus::Closed,
            'closed_by' => $attributes['opened_by'] ?? User::factory(),
            'closing_cash' => '5500.00',
            'expected_cash' => '5500.00',
            'difference' => '0.00',
            'closed_at' => now()->addHours(8),
        ]);
    }
}
