<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Sale>
 */
class SaleFactory extends Factory
{
    protected $model = Sale::class;

    public function definition(): array
    {
        $subtotal = $this->faker->randomFloat(2, 100, 50000);

        return [
            'shop_id' => Shop::factory(),
            'warehouse_id' => null,
            'customer_id' => null,
            'invoice_no' => null,
            'status' => SaleStatus::Draft,
            'subtotal' => number_format($subtotal, 2, '.', ''),
            'discount_total' => '0.00',
            'tax_total' => '0.00',
            'grand_total' => number_format($subtotal, 2, '.', ''),
            'paid_total' => '0.00',
            'notes' => null,
            'posted_at' => null,
            'posted_by' => null,
        ];
    }

    public function posted(): static
    {
        return $this->state(fn (array $attrs) => [
            'status' => SaleStatus::Posted,
            'invoice_no' => 'INV-2081-82-'.str_pad((string) $this->faker->unique()->numberBetween(1, 99999), 6, '0', STR_PAD_LEFT),
            'posted_at' => now(),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn () => ['status' => SaleStatus::Draft]);
    }
}
