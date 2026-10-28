<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ShopSettingsController extends Controller
{
    /**
     * Display the shop settings view.
     */
    public function edit(): View
    {
        $shop = TenantContext::currentShop();
        Gate::authorize('manage', $shop);

        return view('shops.settings', compact('shop'));
    }

    /**
     * Update the shop settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $shop = TenantContext::currentShop();
        Gate::authorize('manage', $shop);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'timezone'],
        ]);

        $shop->update($validated);

        return back()->with('status', trans('shops.settings_updated'));
    }
}
