<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PaymentAllocation>
 */
class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        $shop = Shop::factory()->create();
        $sale = TenantContext::runAs($shop, fn () => Sale::factory()->create(['shop_id' => $shop->id]));
        $payment = TenantContext::runAs($shop, fn () => Payment::factory()->create(['shop_id' => $shop->id]));

        return [
            'shop_id' => $shop->id,
            'payment_id' => $payment->id,
            'sale_id' => $sale->id,
            'amount' => '10.00',
        ];
    }
}
