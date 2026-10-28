<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\ExpenseCategory;
use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    protected $model = ExpenseCategory::class;

    public function definition(): array
    {
        return [
            'shop_id' => Shop::factory(),
            'name' => fake()->randomElement(['Rent', 'Electricity', 'Tea & Refreshments', 'Transportation', 'Salary', 'Stationery', 'Cleaning', 'Internet']),
        ];
    }
}
