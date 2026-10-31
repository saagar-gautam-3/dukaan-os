<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\CreateSupplier;
use App\Actions\Inventory\DeleteSupplier;
use App\Actions\Inventory\QuerySuppliers;
use App\Actions\Inventory\UpdateSupplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StoreSupplierRequest;
use App\Http\Requests\Inventory\UpdateSupplierRequest;
use App\Models\Supplier;
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
}
