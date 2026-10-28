<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has('locale')) {
            $locale = (string) $request->session()->get('locale');
            if (in_array($locale, ['en', 'ne'], true)) {
                app()->setLocale($locale);
            }
        }

        return $next($request);
    }
}
