<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;
use InvalidArgumentException;

class UpdateExpense
{
    public function handle(Expense $expense, array $data): Expense
    {
        if (isset($data['amount']) && bccomp((string) $data['amount'], '0', 2) <= 0) {
            throw new InvalidArgumentException(__('expenses.invalid_amount'));
        }

        $expense->update(array_filter([
            'category_id' => $data['category_id'] ?? $expense->category_id,
            'amount' => isset($data['amount']) ? (string) $data['amount'] : $expense->amount,
            'expense_date' => $data['expense_date'] ?? $expense->expense_date,
            'payment_method' => $data['payment_method'] ?? $expense->payment_method,
            'reference_number' => $data['reference_number'] ?? $expense->reference_number,
            'description' => $data['description'] ?? $expense->description,
        ], fn ($v) => $v !== null));

        return $expense->fresh();
    }
}
