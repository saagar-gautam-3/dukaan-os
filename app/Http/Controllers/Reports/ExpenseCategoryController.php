<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreExpenseCategoryRequest;
use App\Models\ExpenseCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ExpenseCategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', ExpenseCategory::class);

        $categories = ExpenseCategory::withCount('expenses')->orderBy('name')->paginate(25);

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
            ExpenseCategory::create(['name' => $request->validated('name')]);
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

    public function update(StoreExpenseCategoryRequest $request, ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('update', $expenseCategory);

        try {
            $expenseCategory->update(['name' => $request->validated('name')]);
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expense-categories.index')->with('success', __('expenses.category_updated'));
    }

    public function destroy(ExpenseCategory $expenseCategory): RedirectResponse
    {
        $this->authorize('delete', $expenseCategory);

        if ($expenseCategory->expenses()->exists()) {
            return back()->with('error', __('expenses.category_has_expenses'));
        }

        try {
            $expenseCategory->delete();
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expense-categories.index')->with('success', __('expenses.category_deleted'));
    }
}
