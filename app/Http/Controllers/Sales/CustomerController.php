<?php

declare(strict_types=1);

namespace App\Http\Controllers\Sales;

use App\Actions\Sales\ArchiveCustomer;
use App\Actions\Sales\CreateCustomer;
use App\Actions\Sales\QueryCustomerDetails;
use App\Actions\Sales\QueryCustomers;
use App\Actions\Sales\UpdateCustomer;
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
        private readonly CreateCustomer $createCustomer,
        private readonly UpdateCustomer $updateCustomer,
        private readonly ArchiveCustomer $archiveCustomer,
        private readonly QueryCustomerDetails $queryCustomerDetails,
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

        ['ledgerEntries' => $ledgerEntries, 'openSales' => $openSales, 'balance' => $balance, 'headroom' => $headroom, 'reminderLogs' => $reminderLogs] = $this->queryCustomerDetails->handle($customer);

        return view('customers.show', compact(
            'customer',
            'ledgerEntries',
            'openSales',
            'balance',
            'headroom',
            'reminderLogs',
        ));
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
            $customer = $this->createCustomer->handle($request->validated());

            return redirect()->route('customers.index')
                ->with('status', __('sales.customer_saved'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
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
            $this->updateCustomer->handle($customer, $request->validated());

            return redirect()->route('customers.show', $customer)
                ->with('status', __('common.updated_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        try {
            $result = $this->archiveCustomer->handle($customer);

            return redirect()->route('customers.index')
                ->with('status', $result['message']);
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }
}
