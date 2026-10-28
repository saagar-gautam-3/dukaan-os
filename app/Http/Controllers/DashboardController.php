<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Product;
use App\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    /**
     * Show the main tenant dashboard.
     */
    public function index(): View
    {
        $shop = TenantContext::currentShop();
        $membership = TenantContext::currentMembership();

        // Calculate low stock alert count
        $lowStockCount = Product::with('stockBalances')
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(fn (Product $p) => (float) $p->stockBalances->sum('quantity') <= (float) $p->low_stock_threshold)
            ->count();

        $totalProducts = Product::count();

        return view('dashboard', [
            'shop' => $shop,
            'membership' => $membership,
            'lowStockCount' => $lowStockCount,
            'totalProducts' => $totalProducts,
        ]);
    }
}
