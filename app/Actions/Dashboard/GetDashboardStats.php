<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\Product;
use App\Tenancy\TenantContext;

class GetDashboardStats
{
    /**
     * @return array{
     *     totalProducts: int,
     *     lowStockCount: int,
     *     todaySalesTotal: string,
     *     todaySalesCount: int,
     * }
     */
    public function handle(): array
    {
        $shop = TenantContext::currentShop();

        // Low stock count based on per-product threshold
        $lowStockCount = Product::with('stockBalances')
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(fn (Product $p) => (float) $p->stockBalances->sum('quantity') <= (float) $p->low_stock_threshold)
            ->count();

        $totalProducts = Product::where('shop_id', $shop->id)->count();

        return [
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
        ];
    }
}
