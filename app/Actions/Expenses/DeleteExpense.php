<?php

declare(strict_types=1);

namespace App\Actions\Expenses;

use App\Models\Expense;

class DeleteExpense
{
    public function handle(Expense $expense): void
    {
        $expense->delete();
    }
}
