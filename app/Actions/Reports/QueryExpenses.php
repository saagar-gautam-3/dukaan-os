<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Models\Expense;
use App\Models\ExpenseCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

final class QueryExpenses
{
    /** @return array{expenses: LengthAwarePaginator, categories: Collection} */
    public function index(array $filters): array
    {
        $expenses = Expense::with('category')
            ->when($filters['date_from'] ?? null, fn ($query, $value) => $query->whereDate('expense_date', '>=', $value))
            ->when($filters['date_to'] ?? null, fn ($query, $value) => $query->whereDate('expense_date', '<=', $value))
            ->when($filters['category_id'] ?? null, fn ($query, $value) => $query->where('category_id', $value))
            ->orderByDesc('expense_date')->paginate(25)->withQueryString();

        return ['expenses' => $expenses, 'categories' => ExpenseCategory::orderBy('name')->get()];
    }

    public function categories(): Collection
    {
        return ExpenseCategory::orderBy('name')->get();
    }
}
