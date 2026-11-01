<?php

declare(strict_types=1);

use App\Http\Middleware\EnsurePermissionsVersion;
use App\Http\Middleware\EnsurePlatformAdmin;
use App\Http\Middleware\EnsureShopAccess;
use App\Http\Middleware\ResolveTenant;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\SetLocale;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SecurityHeaders::class,
            SetLocale::class,
            EnsurePermissionsVersion::class,
        ]);

        $middleware->alias([
            'tenant.resolve' => ResolveTenant::class,
            'shop.access' => EnsureShopAccess::class,
            'platform.admin' => EnsurePlatformAdmin::class,
            'verified' => EnsureEmailIsVerified::class,
        ]);

        $middleware->priority([
            StartSession::class,
            ShareErrorsFromSession::class,
            AuthenticatesRequests::class,
            EnsurePermissionsVersion::class,
            ResolveTenant::class,
            EnsureShopAccess::class,
            SubstituteBindings::class,
            Authorize::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
