<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Shop;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'category_id' => ExpenseCategory::factory(),
            'amount' => fake()->randomElement(['500.00', '1200.00', '2500.00', '350.00', '8000.00']),
            'expense_date' => now()->toDateString(),
            'payment_method' => fake()->randomElement(['cash', 'bank', 'qr']),
            'reference_number' => null,
            'description' => fake()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
