<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\ExpenseCategory;

final class DeleteExpenseCategory
{
    public function handle(ExpenseCategory $category): bool
    {
        if ($category->expenses()->exists()) {
            return false;
        }

        $category->delete();

        return true;
    }
}
