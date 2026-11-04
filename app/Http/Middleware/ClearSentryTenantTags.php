<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Sentry\State\Scope;
use Symfony\Component\HttpFoundation\Response;

use function Sentry\configureScope;

final class ClearSentryTenantTags
{
    /** @param Closure(Request): (Response) $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (filled(config('sentry.dsn'))) {
            configureScope(static function (Scope $scope): void {
                $scope->removeTag('shop_id');
                $scope->removeTag('user_id');
            });
        }

        return $next($request);
    }
}
