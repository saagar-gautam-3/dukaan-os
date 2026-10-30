<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;
use App\Tenancy\TenantContext;
use InvalidArgumentException;

class CreateExpense
{
    public function handle(int $userId, array $data): Expense
    {
        $shop = TenantContext::shop();

        if (bccomp((string) ($data['amount'] ?? '0'), '0', 2) <= 0) {
            throw new InvalidArgumentException(__('expenses.invalid_amount'));
        }

        return Expense::create([
            'shop_id' => $shop->id,
            'category_id' => $data['category_id'],
            'amount' => (string) $data['amount'],
            'expense_date' => $data['expense_date'],
            'payment_method' => $data['payment_method'] ?? 'cash',
            'reference_number' => $data['reference_number'] ?? null,
            'description' => $data['description'] ?? null,
            'created_by' => $userId,
        ]);
    }
}
