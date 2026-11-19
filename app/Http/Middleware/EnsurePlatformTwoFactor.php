<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformTwoFactor
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isPlatformAdmin()) {
            abort(403);
        }

        $routeName = (string) $request->route()?->getName();
        $setupRoutes = ['platform.2fa.setup', 'platform.2fa.setup.confirm'];
        $challengeRoutes = ['platform.2fa.challenge', 'platform.2fa.challenge.verify'];

        $confirmed = $user->hasPlatformTwoFactorConfirmed();

        if (! $confirmed) {
            if (in_array($routeName, $setupRoutes, true)) {
                return $next($request);
            }

            return redirect()->route('platform.2fa.setup');
        }

        $passedAt = $request->session()->get('platform_2fa_passed_at');
        $passed = is_int($passedAt) && (now()->timestamp - $passedAt) <= (12 * 60 * 60);

        if (! $passed) {
            if (in_array($routeName, $challengeRoutes, true)) {
                return $next($request);
            }

            return redirect()->route('platform.2fa.challenge');
        }

        if (in_array($routeName, array_merge($setupRoutes, $challengeRoutes), true)) {
            return redirect()->route('platform.shops.index');
        }

        return $next($request);
    }
}
