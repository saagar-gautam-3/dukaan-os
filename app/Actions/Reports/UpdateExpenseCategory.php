<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\ExpenseCategory;

final class UpdateExpenseCategory
{
    public function handle(ExpenseCategory $category, array $data): void
    {
        $category->update(['name' => $data['name']]);
    }
}
