<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStats;
use App\Actions\Reports\QueryDailySummary;
use App\Tenancy\TenantContext;
use Illuminate\View\View;
use Throwable;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardStats $getDashboardStats,
        private readonly QueryDailySummary $queryDailySummary,
    ) {}

    /**
     * Show the main tenant dashboard.
     */
    public function index(): View
    {
        $shop = TenantContext::currentShop();
        $membership = TenantContext::currentMembership();
        $stats = $this->getDashboardStats->handle();

        $daily = null;

        try {
            $daily = $this->queryDailySummary->handle($shop);
        } catch (Throwable $e) {
            report($e);
        }

        return view('dashboard', [
            'shop' => $shop,
            'membership' => $membership,
            'lowStockCount' => $stats['lowStockCount'],
            'totalProducts' => $stats['totalProducts'],
            'todaySales' => $stats['todaySales'],
            'todaySaleCount' => $stats['todaySaleCount'],
            'cashReceived' => $stats['cashReceived'],
            'totalUdhar' => $stats['totalUdhar'],
            'udharCustomerCount' => $stats['udharCustomerCount'],
            'estimatedProfit' => $stats['estimatedProfit'],
            'daily' => $daily,
        ]);
    }
}
