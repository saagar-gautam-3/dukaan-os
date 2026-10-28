<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Actions\Dashboard\GetDashboardStats;
use App\Tenancy\TenantContext;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly GetDashboardStats $getDashboardStats,
    ) {}

    /**
     * Show the main tenant dashboard.
     */
    public function index(): View
    {
        $shop = TenantContext::currentShop();
        $membership = TenantContext::currentMembership();
        $stats = $this->getDashboardStats->handle();

        return view('dashboard', [
            'shop' => $shop,
            'membership' => $membership,
            'lowStockCount' => $stats['lowStockCount'],
            'totalProducts' => $stats['totalProducts'],
        ]);
    }
}
