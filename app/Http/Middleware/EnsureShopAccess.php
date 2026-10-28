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
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null || ! TenantContext::has()) {
            abort(403, 'Tenant context missing or unauthorized.');
        }

        $shop = TenantContext::currentShop();
        if (! $shop->is_active) {
            abort(403, 'This shop has been deactivated.');
        }

        // Verify fresh active membership directly against the database
        $membership = ShopUser::query()
            ->where('shop_id', $shop->id)
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();

        if ($membership === null) {
            abort(403, 'Access to this shop is revoked or inactive.');
        }

        return $next($request);
    }
}
