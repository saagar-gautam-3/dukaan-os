<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Actions\Shops\QueryActiveMemberships;
use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

class ResolveTenant
{
    public function __construct(
        protected TenantResolver $resolver
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $this->resolver->resolve($request);

        if ($shop === null || ! TenantContext::has()) {
            $user = $request->user();
            if ($user?->is_platform_admin && app(QueryActiveMemberships::class)->forLogin($user)->isEmpty()) {
                return redirect()->route('platform.shops.index');
            }

            $onMissing = config('tenancy.on_missing_context', 'abort_403');

            if ($onMissing === 'redirect' && Route::has('shops.picker') && ! $request->expectsJson()) {
                return redirect()->route('shops.picker');
            }

            abort(403, 'Tenant context missing or unauthorized.');
        }

        if (filled(config('sentry.dsn'))) {
            configureScope(static function (Scope $scope) use ($request, $shop): void {
                $scope->setTag('shop_id', (string) $shop->id);
                if ($request->user() !== null) {
                    $scope->setTag('user_id', (string) $request->user()->id);
                }
            });
        }

        return $next($request);
    }
}
