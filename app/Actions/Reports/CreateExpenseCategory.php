<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\ExpenseCategory;

final class CreateExpenseCategory
{
    public function handle(array $data): ExpenseCategory
    {
        return ExpenseCategory::create(['name' => $data['name']]);
    }
}
