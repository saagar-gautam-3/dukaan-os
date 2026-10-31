<?php

declare(strict_types=1);

namespace App\Http\Controllers\Reports;

use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Actions\Reports\QueryExpenses;
use App\Http\Controllers\Controller;
use App\Http\Requests\Reports\StoreExpenseRequest;
use App\Models\Expense;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class ExpenseController extends Controller
{
    public function __construct(
        private readonly CreateExpense $createExpense,
        private readonly UpdateExpense $updateExpense,
        private readonly DeleteExpense $deleteExpense,
        private readonly QueryExpenses $queryExpenses,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Expense::class);

        ['expenses' => $expenses, 'categories' => $categories] = $this->queryExpenses->index($request->only(['date_from', 'date_to', 'category_id']));

        return view('expenses.index', compact('expenses', 'categories'));
    }

    public function create(): View
    {
        $this->authorize('create', Expense::class);

        $categories = $this->queryExpenses->categories();

        return view('expenses.form', compact('categories'));
    }

    public function store(StoreExpenseRequest $request): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        try {
            $this->createExpense->handle(auth()->id(), $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expenses.index')->with('success', __('expenses.created'));
    }

    public function edit(Expense $expense): View
    {
        $this->authorize('update', $expense);

        $categories = $this->queryExpenses->categories();

        return view('expenses.form', compact('expense', 'categories'));
    }

    public function update(StoreExpenseRequest $request, Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $this->updateExpense->handle($expense, $request->validated());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expenses.index')->with('success', __('expenses.updated'));
    }

    public function destroy(Expense $expense): RedirectResponse
    {
        $this->authorize('delete', $expense);

        try {
            $this->deleteExpense->handle($expense);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }

        return redirect()->route('expenses.index')->with('success', __('expenses.deleted'));
    }
}
