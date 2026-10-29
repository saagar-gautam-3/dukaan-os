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
        $locale = 'ne'; // default ne per requirement

        if ($request->user() && ! empty($request->user()->locale) && in_array($request->user()->locale, ['en', 'ne'], true)) {
            $locale = $request->user()->locale;
        } elseif ($request->hasSession() && $request->session()->has('locale')) {
            $sessionLocale = (string) $request->session()->get('locale');
            if (in_array($sessionLocale, ['en', 'ne'], true)) {
                $locale = $sessionLocale;
            }
        } elseif ($request->hasCookie('locale')) {
            $cookieLocale = (string) $request->cookie('locale');
            if (in_array($cookieLocale, ['en', 'ne'], true)) {
                $locale = $cookieLocale;
            }
        }

        app()->setLocale($locale);

        return $next($request);
    }
}
