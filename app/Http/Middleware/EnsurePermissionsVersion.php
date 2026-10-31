<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePermissionsVersion
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();
            $sessionKey = 'permissions_version';
            $current = (int) ($user->permissions_version ?? 0);

            if (! $request->session()->has($sessionKey)) {
                $request->session()->put($sessionKey, $current);
            } elseif ((int) $request->session()->get($sessionKey) !== $current) {
                Auth::logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                return redirect()->route('login');
            }
        }

        return $next($request);
    }
}
