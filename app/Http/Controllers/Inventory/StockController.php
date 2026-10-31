<?php

declare(strict_types=1);

namespace App\Http\Controllers\Inventory;

use App\Actions\Inventory\PerformStockAdjustment;
use App\Actions\Inventory\QueryStockAlerts;
use App\Actions\Inventory\QueryStockBalances;
use App\Actions\Inventory\QueryStockForms;
use App\Actions\Inventory\QueryStockMovements;
use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\AdjustStockRequest;
use App\Models\Product;
use App\Models\StockBalance;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class StockController extends Controller
{
    /**
     * Display current stock balances across products and warehouses.
     */
    public function index(Request $request, QueryStockBalances $queryStockBalances, QueryStockForms $queryStockForms): View
    {
        $this->authorize('viewAny', StockBalance::class);

        $balances = $queryStockBalances->execute($request->only(['warehouse_id', 'search', 'filter']));
        $warehouses = $queryStockForms->warehouses();

        return view('stock.index', compact('balances', 'warehouses'));
    }

    /**
     * Display movement history for a specific product.
     */
    public function history(Product $product, Request $request, QueryStockMovements $queryStockMovements, QueryStockForms $queryStockForms): View
    {
        $this->authorize('view', $product);

        $movements = $queryStockMovements->execute($product, $request->only(['warehouse_id', 'date_from', 'date_to']));
        $warehouses = $queryStockForms->warehouses();

        return view('stock.history', compact('product', 'movements', 'warehouses'));
    }

    /**
     * Show form for stock adjustment.
     */
    public function adjustForm(QueryStockForms $queryStockForms): View
    {
        $this->authorize('adjust', StockBalance::class);

        ['products' => $products, 'warehouses' => $warehouses] = $queryStockForms->adjustment();

        return view('stock.adjust', compact('products', 'warehouses'));
    }

    /**
     * Process stock adjustment.
     */
    public function adjust(AdjustStockRequest $request, PerformStockAdjustment $performStockAdjustment): RedirectResponse
    {
        try {
            $performStockAdjustment->handle($request->validated(), (int) auth()->id());

            return redirect()->route('stock.index')->with('status', __('inventory.adjustment_success'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
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
