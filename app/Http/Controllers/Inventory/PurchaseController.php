<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\PostPurchase;
use App\Enums\PurchaseStatus;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class PurchaseController extends Controller
{
    /**
     * Display a listing of purchases.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', Purchase::class);

        $query = Purchase::with(['supplier', 'warehouse', 'createdBy'])->latest();

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        if ($request->filled('supplier_id')) {
            $query->where('supplier_id', $request->integer('supplier_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($q) use ($search) {
                $q->where('purchase_no', 'like', "%{$search}%")
                    ->orWhereHas('supplier', fn ($sq) => $sq->where('name', 'like', "%{$search}%"));
            });
        }

        $purchases = $query->paginate(15)->withQueryString();
        $suppliers = Supplier::orderBy('name')->get();

        return view('purchases.index', compact('purchases', 'suppliers'));
    }

    /**
     * Show the form for creating a new purchase draft.
     */
    public function create(): View
    {
        Gate::authorize('create', Purchase::class);

        $suppliers = Supplier::orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();
        $products = Product::where('is_active', true)->with('unit')->orderBy('name')->get();

        return view('purchases.create', compact('suppliers', 'warehouses', 'products'));
    }

    /**
     * Store a newly created draft purchase with line items in one transaction.
     */
    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Purchase::class);

        $validated = $request->validate([
            'supplier_id' => ['required', 'exists:suppliers,id'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'purchase_no' => ['required', 'string', 'max:50'],
            'purchase_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'exists:products,id'],
            'items.*.quantity' => ['required', 'numeric', 'gt:0'],
            'items.*.unit_cost' => ['required', 'numeric', 'min:0'],
            'items.*.discount' => ['nullable', 'numeric', 'min:0'],
        ]);

        $purchase = DB::transaction(function () use ($validated) {
            $subtotal = 0.00;
            $discountTotal = 0.00;

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $discount = (float) ($itemData['discount'] ?? 0);
                $lineTotal = ($qty * $cost) - $discount;

                $subtotal += ($qty * $cost);
                $discountTotal += $discount;
            }

            $grandTotal = $subtotal - $discountTotal;

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'warehouse_id' => $validated['warehouse_id'],
                'purchase_no' => $validated['purchase_no'],
                'purchase_date' => $validated['purchase_date'],
                'status' => PurchaseStatus::Draft,
                'subtotal' => $subtotal,
                'discount_total' => $discountTotal,
                'tax_total' => 0.00,
                'grand_total' => $grandTotal,
                'notes' => $validated['notes'] ?? null,
                'created_by' => auth()->id(),
            ]);

            foreach ($validated['items'] as $itemData) {
                $qty = (float) $itemData['quantity'];
                $cost = (float) $itemData['unit_cost'];
                $discount = (float) ($itemData['discount'] ?? 0);
                $lineTotal = ($qty * $cost) - $discount;

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $itemData['product_id'],
                    'quantity' => $qty,
                    'unit_cost' => $cost,
                    'discount' => $discount,
                    'line_total' => $lineTotal,
                ]);
            }

            return $purchase;
        });

        return redirect()->route('purchases.show', $purchase)->with('status', __('purchases.created_success'));
    }

    /**
     * Display the specified purchase. Read-only for posted purchases.
     */
    public function show(Purchase $purchase): View
    {
        Gate::authorize('view', $purchase);

        $purchase->load(['supplier', 'warehouse', 'createdBy', 'items.product.unit']);

        return view('purchases.show', compact('purchase'));
    }

    /**
     * Post a draft purchase, receiving stock and marking it immutable.
     */
    public function post(Purchase $purchase, PostPurchase $postPurchase): RedirectResponse
    {
        Gate::authorize('post', $purchase);

        $postPurchase->execute($purchase, auth()->id());

        return redirect()->route('purchases.show', $purchase)->with('status', __('purchases.posted_success'));
    }

    /**
     * Remove the draft purchase from storage.
     */
    public function destroy(Purchase $purchase): RedirectResponse
    {
        Gate::authorize('delete', $purchase);

        $purchase->items()->delete();
        $purchase->delete();

        return redirect()->route('purchases.index')->with('status', __('purchases.deleted_success'));
    }
}
