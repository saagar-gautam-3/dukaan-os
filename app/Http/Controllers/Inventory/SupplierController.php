<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateSupplier;
use App\Actions\Inventory\DeleteSupplier;
use App\Actions\Inventory\QuerySuppliers;
use App\Actions\Inventory\RecordSupplierPayment;
use App\Actions\Inventory\UpdateSupplier;
use App\Enums\LedgerEntryType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierPaymentRequest;
use App\Http\Requests\Inventory\StoreSupplierRequest;
use App\Http\Requests\Inventory\UpdateSupplierRequest;
use App\Models\IdempotencyKey;
use App\Models\Supplier;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
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

    public function createPayment(Supplier $supplier): View
    {
        $this->authorize('pay', $supplier);

        $balance = $supplier->ledgerBalance();
        $entries = $supplier->ledgerEntries()->with('createdBy')->latest()->paginate(20);

        return view('suppliers.payments.create', compact('supplier', 'balance', 'entries'));
    }

    public function storePayment(StoreSupplierPaymentRequest $request, Supplier $supplier, RecordSupplierPayment $recordSupplierPayment): RedirectResponse
    {
        $this->authorize('pay', $supplier);

        $idemKey = $request->string('idempotency_key')->toString() ?: null;
        if ($idemKey !== null) {
            $existing = IdempotencyKey::where('shop_id', (int) TenantContext::getShopId())
                ->where('key', $idemKey)
                ->where('action', 'supplier_payment')
                ->first();

            if ($existing) {
                return redirect()->route('suppliers.dues')
                    ->with('status', __('suppliers.payment_recorded'));
            }
        }

        try {
            $recordSupplierPayment->handle($supplier, (int) auth()->id(), [
                'amount' => (string) $request->input('amount'),
                'method' => (string) $request->input('payment_method'),
                'reference_number' => $request->string('reference_number')->toString() ?: null,
                'notes' => $request->string('notes')->toString() ?: null,
                'payment_date' => $request->input('payment_date'),
                'idempotency_key' => $idemKey,
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

    public function dues(): View
    {
        $this->authorize('viewAny', Supplier::class);

        $suppliers = Supplier::query()
            ->withSum('ledgerEntries as debit_sum', 'debit')
            ->withSum('ledgerEntries as credit_sum', 'credit')
            ->withMax(['ledgerEntries as last_payment_at' => function ($q) {
                $q->where('entry_type', LedgerEntryType::SupplierPaymentCredit->value);
            }], 'created_at')
            ->withMin(['purchases as oldest_purchase_at' => function ($q) {
                $q->where('status', 'posted');
            }], 'purchase_date')
            ->get()
            ->map(function (Supplier $s) {
                $s->balance = Money::subtract((string) ($s->debit_sum ?? 0), (string) ($s->credit_sum ?? 0), 2);

                return $s;
            })
            ->filter(fn (Supplier $s) => Money::compare((string) $s->balance, '0', 2) > 0)
            ->sortByDesc(fn (Supplier $s) => (string) $s->balance)
            ->values();

        $totalPayable = $suppliers->reduce(function (string $carry, Supplier $s) {
            return Money::add($carry, (string) $s->balance, 2);
        }, '0.00');

        return view('suppliers.dues', compact('suppliers', 'totalPayable'));
    }
}
