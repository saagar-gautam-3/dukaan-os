<?php

declare(strict_types=1);

namespace App\Http\Controllers;

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

        return view('dashboard', [
            'shop' => $shop,
            'membership' => $membership,
        ]);
    }
}
