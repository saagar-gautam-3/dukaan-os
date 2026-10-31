<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class QueryExpenseCategories
{
    public function index(): LengthAwarePaginator
    {
        return ExpenseCategory::withCount('expenses')->orderBy('name')->paginate(25);
    }
}
