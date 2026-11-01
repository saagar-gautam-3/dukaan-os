<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\ShopUser;
use App\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureShopAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! TenantContext::has()) {
            abort(403, __('errors.tenant_context_missing'));
        }

        $shop = TenantContext::currentShop();
        if (! $shop->is_active) {
            abort(403, __('errors.shop_deactivated'));
        }

        // Verify fresh active membership directly against the database
        $membership = ShopUser::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            abort(403, __('errors.shop_access_revoked'));
        }

        return $next($request);
    }
}
