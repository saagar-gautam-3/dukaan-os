<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CashMovementType;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Shop;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashMovementFactory extends Factory
{
    protected $model = CashMovement::class;

    public function definition(): array
    {
        if (TenantContext::has()) {
            $shop = TenantContext::shop();
            $session = CashSession::factory()->create(['shop_id' => $shop->id]);
        } else {
            $shop = Shop::factory()->create();
            $session = TenantContext::runAs($shop, fn () => CashSession::factory()->create(['shop_id' => $shop->id]));
        }

        return [
            'shop_id' => $shop->id,
            'cash_session_id' => $session->id,
            'type' => fake()->randomElement(CashMovementType::cases()),
            'amount' => '500.00',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
