<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Tenancy\TenantContext;
use App\Tenancy\TenantResolver;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(
        protected TenantResolver $resolver
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $shop = $this->resolver->resolve($request);

        if ($shop === null || ! TenantContext::has()) {
            $onMissing = config('tenancy.on_missing_context', 'abort_403');

            if ($onMissing === 'redirect' && Route::has('shops.picker') && ! $request->expectsJson()) {
                return redirect()->route('shops.picker');
            }

            abort(403, 'Tenant context missing or unauthorized.');
        }

        return $next($request);
    }
}
