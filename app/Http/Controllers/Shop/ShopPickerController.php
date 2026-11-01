<?php

declare(strict_types=1);

namespace App\Http\Controllers\Shop;

use App\Actions\Shops\QueryActiveMemberships;
use App\Actions\Tenancy\CreateShopForUser;
use App\Http\Controllers\Controller;
use App\Models\Shop;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Throwable;

class ShopPickerController extends Controller
{
    public function create(): View
    {
        return view('shops.create');
    }

    public function store(Request $request, CreateShopForUser $createShop): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'business_type' => ['required', Rule::in(['kirana', 'clothing', 'electronics', 'pharmacy', 'hardware', 'general'])],
        ]);

        try {
            $result = $createShop->execute($request->user(), $validated);
            $request->session()->put('current_shop_id', $result['shop']->id);

            return redirect()->route('dashboard')->with('status', __('shops.created_success'));
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput()->with('error', __('common.error_generic'));
        }
    }

    /**
     * Display a list of available shops for the authenticated user.
     */
    public function index(Request $request, QueryActiveMemberships $membershipsQuery): View|RedirectResponse
    {
        $memberships = $membershipsQuery->forUser($request->user());

        if ($request->user()->is_platform_admin && $memberships->isEmpty()) {
            return redirect()->route('platform.shops.index');
        }

        return view('shops.picker', [
            'memberships' => $memberships,
            'currentShopId' => $request->session()->get('current_shop_id'),
            'isPlatformAdmin' => $request->user()->is_platform_admin,
        ]);
    }

    /**
     * Switch active shop in session.
     */
    public function switch(Request $request, Shop $shop, QueryActiveMemberships $membershipsQuery): RedirectResponse
    {
        $membership = $membershipsQuery->find($request->user(), (int) $shop->id);

        if ($membership === null || ! $shop->is_active) {
            abort(403, __('errors.shop_switch_unauthorized'));
        }

        $request->session()->put('current_shop_id', $shop->id);

        return redirect()->route('dashboard')->with('status', trans('shops.switched_success', ['name' => $shop->name]));
    }
}
