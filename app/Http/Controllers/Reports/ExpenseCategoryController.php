<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Actions\Reports\CreateExpenseCategory;
use App\Actions\Reports\DeleteExpenseCategory;
use App\Actions\Reports\QueryExpenseCategories;
use App\Actions\Reports\UpdateExpenseCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreExpenseCategoryRequest;
use App\Http\Requests\Reports\UpdateExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ExpenseCategoryController extends Controller
{
    public function __construct(
        private readonly CreateExpenseCategory $createCategory,
        private readonly UpdateExpenseCategory $updateCategory,
        private readonly DeleteExpenseCategory $deleteCategory,
        private readonly QueryExpenseCategories $queryCategories,
    ) {}

    public function index(): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = $this->queryCategories->index();

        return view('expense-categories.index', compact('categories'));
    }

    public function create(): View
    {
        $this->authorize('create', ExpenseCategory::class);

        return view('expense-categories.form');
    }

    public function store(StoreExpenseCategoryRequest $request): RedirectResponse
    {
        $this->authorize('create', ExpenseCategory::class);

        try {
            $this->createCategory->handle($request->validated());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expense-categories.index')->with('success', __('expenses.category_created'));
    }

    public function edit(ExpenseCategory $expenseCategory): View
    {
        $this->authorize('update', $expenseCategory);

        return view('expense-categories.form', ['category' => $expenseCategory]);
    }

    public function update(UpdateExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        try {
            $this->updateCategory->handle($expenseCategory, $request->validated());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expense-categories.index')->with('success', __('expenses.category_updated'));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        try {
            if (! $this->deleteCategory->handle($expenseCategory)) {
                return back()->with('error', __('expenses.category_has_expenses'));
            }
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expense-categories.index')->with('success', __('expenses.category_deleted'));
    }
}
