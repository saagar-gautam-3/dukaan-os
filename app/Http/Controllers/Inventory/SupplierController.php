<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateSupplier;
use App\Actions\Inventory\DeleteSupplier;
use App\Actions\Inventory\QuerySupplierDues;
use App\Actions\Inventory\QuerySupplierLedger;
use App\Actions\Inventory\QuerySuppliers;
use App\Actions\Inventory\RecordSupplierPayment;
use App\Actions\Inventory\UpdateSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierPaymentRequest;
use App\Http\Requests\Inventory\StoreSupplierRequest;
use App\Http\Requests\Inventory\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;
use Throwable;

class SupplierController extends Controller
{
    public function index(Request $request, QuerySuppliers $querySuppliers): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = $querySuppliers->execute($request->only(['search']));

        return view('suppliers.index', compact('suppliers'));
    }

    public function create(): View
    {
        $this->authorize('create', Supplier::class);

        return view('suppliers.form');
    }

    public function store(StoreSupplierRequest $request, CreateSupplier $createSupplier): RedirectResponse
    {
        try {
            $createSupplier->execute($request->validated());

            return redirect()->route('suppliers.index')->with('status', __('suppliers.created_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function edit(Supplier $supplier): View
    {
        $this->authorize('update', $supplier);

        return view('suppliers.form', compact('supplier'));
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier, UpdateSupplier $updateSupplier): RedirectResponse
    {
        try {
            $updateSupplier->execute($supplier, $request->validated());

            return redirect()->route('suppliers.index')->with('status', __('suppliers.updated_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function destroy(Supplier $supplier, DeleteSupplier $deleteSupplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        try {
            $deleteSupplier->execute($supplier);

            return redirect()->route('suppliers.index')->with('status', __('suppliers.deleted_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }

    public function createPayment(Supplier $supplier, QuerySupplierLedger $querySupplierLedger): View
    {
        $this->authorize('pay', $supplier);

        $balance = $supplier->ledgerBalance();
        $entries = $querySupplierLedger->handle($supplier);
        $shopTimezone = (string) ($supplier->shop->timezone ?? config('app.timezone'));

        return view('suppliers.payments.create', compact('supplier', 'balance', 'entries', 'shopTimezone'));
    }

    public function storePayment(StoreSupplierPaymentRequest $request, Supplier $supplier, RecordSupplierPayment $recordSupplierPayment): RedirectResponse
    {
        $this->authorize('pay', $supplier);

        try {
            $recordSupplierPayment->handle($supplier, (int) auth()->id(), [
                'amount' => (string) $request->input('amount'),
                'method' => (string) $request->input('payment_method'),
                'reference_number' => $request->string('reference_number')->toString() ?: null,
                'notes' => $request->string('notes')->toString() ?: null,
                'payment_date' => $request->input('payment_date'),
                'idempotency_key' => $request->string('idempotency_key')->toString() ?: null,
            ]);

            return redirect()->route('suppliers.dues')
                ->with('status', __('suppliers.payment_recorded'));
        } catch (InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    public function dues(QuerySupplierDues $querySupplierDues): View
    {
        $this->authorize('viewAny', Supplier::class);

        $dues = $querySupplierDues->handle();

        return view('suppliers.dues', [
            'suppliers' => $dues['suppliers'],
            'totalPayable' => $dues['total_payable'],
        ]);
    }
}
