<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Customer;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\User;
use App\Policies\CustomerPolicy;
use App\Policies\SalePolicy;
use App\Policies\StockPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Blade;
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

        Gate::policy(StockBalance::class, StockPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);

        Blade::directive('npr', function (string $expression): string {
            return "<?php echo \App\Support\NepaliNumber::formatNpr({$expression}); ?>";
        });

        // Blueprint macro on parent tenant-owned tables to support composite foreign keys
        Blueprint::macro('tenantCompositeIndex', function () {
            /** @var Blueprint $this */
            return $this->unique(['shop_id', 'id']);
        });

        // Blueprint macro on child tables enforcing tenant-safe composite foreign keys
        Blueprint::macro('compositeTenantForeign', function (string $parentColumn, string $parentTable, string $parentKey = 'id') {
            /** @var Blueprint $this */
            return $this->foreign(['shop_id', $parentColumn])
                ->references(['shop_id', $parentKey])
                ->on($parentTable)
                ->cascadeOnDelete();
        });
    }
}
