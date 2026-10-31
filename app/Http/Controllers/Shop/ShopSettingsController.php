<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Shop\UpdateShopSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Shop\UpdateShopSettingsRequest;
use App\Tenancy\TenantContext;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Throwable;

class ShopSettingsController extends Controller
{
    /**
     * Display the shop settings view.
     */
    public function edit(): View
    {
        $shop = TenantContext::currentShop();
        $this->authorize('manage', $shop);

        return view('shops.form', compact('shop'));
    }

    /**
     * Update the shop settings.
     */
    public function update(UpdateShopSettingsRequest $request, UpdateShopSettings $updateShopSettings): RedirectResponse
    {
        $shop = TenantContext::currentShop();
        $this->authorize('manage', $shop);

        try {
            $updateShopSettings->execute($shop, $request->validated());

            return back()->with('status', trans('shops.settings_updated'));
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }
}
