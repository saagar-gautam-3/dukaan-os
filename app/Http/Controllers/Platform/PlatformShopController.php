<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PlatformShopController extends Controller
{
    public function index(): View
    {
        $shops = Shop::withCount(['users', 'sales'])->orderBy('name')->paginate(50);

        return view('platform.shops.index', compact('shops'));
    }

    public function toggle(Shop $shop): RedirectResponse
    {
        $old = $shop->is_active;
        $shop->is_active = ! $shop->is_active;
        $shop->save();

        AuditLog::create([
            'shop_id' => $shop->id,
            'user_id' => auth()->id(),
            'event' => 'platform_shop_toggled',
            'auditable_type' => $shop->getMorphClass(),
            'auditable_id' => $shop->id,
            'old_values' => ['is_active' => $old],
            'new_values' => ['is_active' => $shop->is_active],
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);

        return back()->with('status', __('platform.shop_toggled'));
    }
}
