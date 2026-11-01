<?php

declare(strict_types=1);

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
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
        $shop->is_active = ! $shop->is_active;
        $shop->save();

        return back()->with('status', __('platform.shop_toggled'));
    }
}
