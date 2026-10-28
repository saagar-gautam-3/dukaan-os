<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'user_id' => User::factory(),
            'customer_id' => null,
            'payment_no' => 'PAY-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'method' => PaymentMethod::Cash,
            'status' => PaymentStatus::Verified,
            'amount' => $this->faker->randomFloat(2, 10, 10000),
            'reference_number' => null,
            'received_at' => now(),
            'notes' => null,
            'idempotency_key' => null,
        ];
    }

    public function qrPending(): static
    {
        return $this->state(fn () => [
            'method' => PaymentMethod::Qr,
            'status' => PaymentStatus::Pending,
        ]);
    }
}
