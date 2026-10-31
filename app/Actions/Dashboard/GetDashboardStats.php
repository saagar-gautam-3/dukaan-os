<?php

declare(strict_types=1);

namespace App\Actions\Dashboard;

use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Services\ProfitReportService;
use App\Support\Money;
use App\Tenancy\TenantContext;

class GetDashboardStats
{
    public function __construct(
        private readonly ProfitReportService $profitService,
    ) {}

    /**
     * @return array{
     *     totalProducts: int,
     *     lowStockCount: int,
     *     todaySales: string,
     *     todaySaleCount: int,
     *     cashReceived: string,
     *     totalUdhar: string,
     *     udharCustomerCount: int,
     *     estimatedProfit: string,
     * }
     */
    public function handle(): array
    {
        $shop = TenantContext::currentShop();
        $shopTz = $shop->timezone ?: 'Asia/Kathmandu';

        // Date range for "today" in shop timezone, converted to UTC
        $range = $this->profitService->resolveDateRange('today', $shopTz);

        // Profit summary (uses UTC-bounded queries)
        $summary = $this->profitService->summary($shop->id, $range);

        // Low stock count based on per-product threshold
        $lowStockCount = Product::with('stockBalances')
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->filter(fn (Product $p) => Money::compare(
                $p->stockBalances->reduce(fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3), '0.000'),
                (string) $p->low_stock_threshold,
                3
            ) <= 0)
            ->count();

        $totalProducts = Product::where('shop_id', $shop->id)->count();

        $udharBalance = (string) (CustomerLedgerEntry::where('shop_id', $shop->id)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance') ?? '0.00');

        $udharCustomerCount = CustomerLedgerEntry::where('shop_id', $shop->id)
            ->groupBy('customer_id')
            ->havingRaw('(SUM(debit) - SUM(credit)) > 0')
            ->pluck('customer_id')
            ->count();

        return [
            'totalProducts' => $totalProducts,
            'lowStockCount' => $lowStockCount,
            'todaySales' => $summary['gross_sales'],
            'todaySaleCount' => $summary['sale_count'],
            'cashReceived' => $summary['cash_received'],
            'totalUdhar' => $udharBalance,
            'udharCustomerCount' => $udharCustomerCount,
            'estimatedProfit' => $summary['gross_profit'],
        ];
    }
}
