<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(TenantContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasPermissionInCurrentShop($ability)) {
                return true;
            }

            return null;
        });
    }
}
