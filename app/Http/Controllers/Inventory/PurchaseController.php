<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\DeletePurchase;
use App\Actions\Inventory\PostPurchase;
use App\Actions\Inventory\QueryPurchaseFormData;
use App\Actions\Inventory\QueryPurchases;
use App\Actions\Inventory\StorePurchase;
use App\Exceptions\ImmutableRecordException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\StorePurchaseRequest;
use App\Models\Purchase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index(Request $request, QueryPurchases $queryPurchases, QueryPurchaseFormData $formData): View
    {
        $this->authorize('viewAny', Purchase::class);

        $purchases = $queryPurchases->execute($request->only(['status', 'supplier_id', 'search']));
        $suppliers = $formData->suppliers();

        return view('purchases.index', compact('purchases', 'suppliers'));
    }

    /**
     * Show the form for creating a new purchase draft.
     */
    public function create(QueryPurchaseFormData $formData): View
    {
        $this->authorize('create', Purchase::class);

        ['suppliers' => $suppliers, 'warehouses' => $warehouses, 'products' => $products] = $formData->handle();

        return view('purchases.form', compact('suppliers', 'warehouses', 'products'));
    }

    /**
     * Store a newly created draft purchase with line items in one transaction.
     */
    public function store(StorePurchaseRequest $request, StorePurchase $storePurchase): RedirectResponse
    {
        try {
            $purchase = $storePurchase->execute($request->validated(), auth()->id());

            return redirect()->route('purchases.show', $purchase)->with('status', __('purchases.created_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Display the specified purchase. Read-only for posted purchases.
     */
    public function show(Purchase $purchase): View
    {
        $this->authorize('view', $purchase);

        $purchase->load(['supplier', 'warehouse', 'createdBy', 'items.product.unit']);

        return view('purchases.show', compact('purchase'));
    }

    /**
     * Post a draft purchase, receiving stock and marking it immutable.
     */
    public function post(Purchase $purchase, PostPurchase $postPurchase): RedirectResponse
    {
        $this->authorize('post', $purchase);

        try {
            $postPurchase->execute($purchase, auth()->id());

            return redirect()->route('purchases.show', $purchase)->with('status', __('purchases.posted_success'));
        } catch (ImmutableRecordException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Remove the draft purchase from storage.
     */
    public function destroy(Purchase $purchase, DeletePurchase $deletePurchase): RedirectResponse
    {
        $this->authorize('delete', $purchase);

        try {
            $deletePurchase->execute($purchase);

            return redirect()->route('purchases.index')->with('status', __('purchases.deleted_success'));
        } catch (ImmutableRecordException $e) {
            return back()->with('error', $e->getMessage());
        } catch (Throwable $e) {
            report($e);

            return back()->with('error', __('common.error_generic'));
        }
    }
}
