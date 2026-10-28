<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\QueryCustomers;
use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class CustomerController extends Controller
{
    public function __construct(
        private readonly QueryCustomers $queryCustomers,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Customer::class);

        $customers = $this->queryCustomers->handle(
            $request->string('search')->toString() ?: null
        );

        return view('customers.index', compact('customers'));
    }

    public function show(Customer $customer): View
    {
        $this->authorize('view', $customer);

        $customer->load(['sales' => fn ($q) => $q->orderByDesc('created_at')->limit(20)]);

        return view('customers.show', compact('customer'));
    }

    public function create(): View
    {
        $this->authorize('create', Customer::class);

        return view('customers.form');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        try {
            Customer::create($request->validated());

            return redirect()->route('customers.index')
                ->with('status', __('sales.new_customer').' saved.');
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.unexpected_error'));
        }
    }

    public function edit(Customer $customer): View
    {
        $this->authorize('update', $customer);

        return view('customers.form', compact('customer'));
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        try {
            $customer->update($request->validated());

            return redirect()->route('customers.show', $customer)
                ->with('status', __('common.updated_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.unexpected_error'));
        }
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        try {
            $customer->delete();

            return redirect()->route('customers.index')
                ->with('status', __('common.deleted_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.unexpected_error'));
        }
    }
}
