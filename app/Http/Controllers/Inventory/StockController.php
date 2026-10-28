<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class StockController extends Controller
{
    /**
     * Display current stock balances across products and warehouses.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', StockBalance::class);

        $query = StockBalance::with(['product.unit', 'product.category', 'warehouse']);

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->whereHas('product', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        if ($request->input('filter') === 'low_stock') {
            $query->whereHas('product', function ($q) {
                $q->whereColumn('stock_balances.quantity', '<=', 'products.min_stock_alert')
                    ->where('stock_balances.quantity', '>', 0);
            });
        } elseif ($request->input('filter') === 'out_of_stock') {
            $query->where('quantity', '<=', 0);
        }

        $balances = $query->paginate(20)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.index', compact('balances', 'warehouses'));
    }

    /**
     * Display movement history for a specific product.
     */
    public function history(Product $product, Request $request): View
    {
        Gate::authorize('view', $product);

        $query = StockMovement::where('product_id', $product->id)
            ->with(['warehouse', 'user'])
            ->latest();

        if ($request->filled('warehouse_id')) {
            $query->where('warehouse_id', $request->integer('warehouse_id'));
        }

        $movements = $query->paginate(25)->withQueryString();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.history', compact('product', 'movements', 'warehouses'));
    }

    /**
     * Show form for stock adjustment.
     */
    public function adjustForm(): View
    {
        Gate::authorize('adjust', StockBalance::class);

        $products = Product::where('is_active', true)->with('unit')->orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.adjust', compact('products', 'warehouses'));
    }

    /**
     * Process stock adjustment.
     */
    public function adjust(Request $request, AdjustStock $adjustStock): RedirectResponse
    {
        Gate::authorize('adjust', StockBalance::class);

        $validated = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', 'exists:products,id'],
            'type' => ['required', 'in:in,out'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:255'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'],
        ]);

        $warehouse = Warehouse::findOrFail($validated['warehouse_id']);
        $product = Product::findOrFail($validated['product_id']);

        $adjustStock->execute(
            warehouse: $warehouse,
            items: [
                [
                    'product_id' => $product->id,
                    'type' => $validated['type'],
                    'quantity' => $validated['quantity'],
                    'unit_cost' => $validated['unit_cost'] ?? $product->cost_price,
                ],
            ],
            reason: $validated['reason'],
            userId: auth()->id()
        );

        return redirect()->route('stock.index')->with('status', __('inventory.adjustment_success'));
    }

    /**
     * Display low-stock alerts.
     */
    public function alerts(): View
    {
        Gate::authorize('viewAny', StockBalance::class);

        // Fetch products where total stock across all warehouses is <= min_stock_alert
        $products = Product::with(['unit', 'category', 'stockBalances'])
            ->where('is_active', true)
            ->where('min_stock_alert', '>', 0)
            ->get()
            ->filter(function (Product $product) {
                $totalStock = (float) $product->stockBalances->sum('quantity');

                return $totalStock <= (float) $product->min_stock_alert;
            });

        return view('stock.alerts', compact('products'));
    }
}
