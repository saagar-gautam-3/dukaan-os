<?php

declare(strict_types=1);

namespace App\Tenancy;

use App\Models\Shop;
use App\Models\ShopUser;
use Illuminate\Http\Request;

class TenantResolver
{
    /**
     * Resolve and bind tenant context from the current request session.
     */
    public function resolve(Request $request): ?Shop
    {
        $user = $request->user();
        if ($user === null) {
            TenantContext::reset();

            return null;
        }

        $session = $request->hasSession() ? $request->session() : null;
        $shopId = $session?->get('current_shop_id');

        // If no shop is stored in session, check if user has only 1 active shop
        if ($shopId === null && $session !== null) {
            $onlyMembership = ShopUser::query()
                ->where('user_id', $user->id)
                ->where('status', 'active')
                ->whereHas('shop', fn ($q) => $q->where('is_active', true))
                ->with('shop')
                ->first();

            if ($onlyMembership !== null) {
                $shopId = $onlyMembership->shop_id;
                $session->put('current_shop_id', $shopId);
            }
        }

        if ($shopId === null) {
            TenantContext::reset();

            return null;
        }

        /** @var ShopUser|null $membership */
        $membership = ShopUser::query()
            ->where('shop_id', $shopId)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->whereHas('shop', fn ($q) => $q->where('is_active', true))
            ->with(['shop', 'role.permissions'])
            ->first();

        if ($membership === null || $membership->shop === null) {
            $session?->forget('current_shop_id');
            TenantContext::reset();

            return null;
        }

        TenantContext::setShop($membership->shop, $membership);

        return $membership->shop;
    }
}
