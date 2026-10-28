<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\AdjustStock;
use App\Actions\Inventory\QueryStockAlerts;
use App\Actions\Inventory\QueryStockBalances;
use App\Actions\Inventory\QueryStockMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Warehouse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    /**
     * Display current stock balances across products and warehouses.
     */
    public function index(Request $request, QueryStockBalances $queryStockBalances): View
    {
        $this->authorize('viewAny', StockBalance::class);

        $balances = $queryStockBalances->execute($request->all());
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.index', compact('balances', 'warehouses'));
    }

    /**
     * Display movement history for a specific product.
     */
    public function history(Product $product, Request $request, QueryStockMovements $queryStockMovements): View
    {
        $this->authorize('view', $product);

        $movements = $queryStockMovements->execute($product, $request->all());
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.history', compact('product', 'movements', 'warehouses'));
    }

    /**
     * Show form for stock adjustment.
     */
    public function adjustForm(): View
    {
        $this->authorize('adjust', StockBalance::class);

        $products = Product::where('is_active', true)->with('unit')->orderBy('name')->get();
        $warehouses = Warehouse::orderBy('name')->get();

        return view('stock.adjust', compact('products', 'warehouses'));
    }

    /**
     * Process stock adjustment.
     */
    public function adjust(AdjustStockRequest $request, AdjustStock $adjustStock): RedirectResponse
    {
        try {
            $validated = $request->validated();
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
        } catch (Throwable $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }
    }

    /**
     * Display low-stock alerts.
     */
    public function alerts(QueryStockAlerts $queryStockAlerts): View
    {
        $this->authorize('viewAny', StockBalance::class);

        $products = $queryStockAlerts->execute();

        return view('stock.alerts', compact('products'));
    }
}
