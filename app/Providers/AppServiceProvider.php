<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Sale;
use App\Models\ShopUser;
use App\Models\StockBalance;
use App\Models\SupplierLedgerEntry;
use App\Models\User;
use App\Policies\CashSessionPolicy;
use App\Policies\CustomerLedgerEntryPolicy;
use App\Policies\CustomerPolicy;
use App\Policies\ExpenseCategoryPolicy;
use App\Policies\ExpensePolicy;
use App\Policies\SalePolicy;
use App\Policies\ShopUserPolicy;
use App\Policies\StockPolicy;
use App\Policies\SupplierLedgerEntryPolicy;
use App\Tenancy\TenantContext;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

use function Sentry\configureScope;

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
        Paginator::useBootstrapFive();

        Gate::before(function (User $user, string $ability): ?bool {
            if ($user->hasPermissionInCurrentShop($ability)) {
                return true;
            }

            return null;
        });

        Gate::policy(StockBalance::class, StockPolicy::class);
        Gate::policy(Sale::class, SalePolicy::class);
        Gate::policy(ShopUser::class, ShopUserPolicy::class);
        Gate::policy(Customer::class, CustomerPolicy::class);
        Gate::policy(CustomerLedgerEntry::class, CustomerLedgerEntryPolicy::class);
        Gate::policy(SupplierLedgerEntry::class, SupplierLedgerEntryPolicy::class);
        Gate::policy(ExpenseCategory::class, ExpenseCategoryPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(CashSession::class, CashSessionPolicy::class);

        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by($request->ip())
            ->response(fn (Request $request, array $headers) => response()->view(
                'errors.rate-limited',
                ['message' => __('auth.rate_limited')],
                429,
                $headers,
            )));
        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute(5)->by($request->ip()));
        RateLimiter::for('post-sale', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('payment', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('collect', fn (Request $request) => Limit::perMinute(20)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('remind', fn (Request $request) => Limit::perMinute(10)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('staff-write', fn (Request $request) => Limit::perMinute(30)->by($request->user()?->id ?: $request->ip()));

        Queue::before(function (): void {
            TenantContext::reset();
            if (filled(config('sentry.dsn'))) {
                configureScope(static function ($scope): void {
                    $scope->removeTag('shop_id');
                    $scope->removeTag('user_id');
                });
            }
        });

        Queue::after(function (): void {
            TenantContext::reset();
            if (filled(config('sentry.dsn'))) {
                configureScope(static function ($scope): void {
                    $scope->removeTag('shop_id');
                    $scope->removeTag('user_id');
                });
            }
        });

        Queue::failing(function (): void {
            TenantContext::reset();
        });

        Queue::exceptionOccurred(function (): void {
            TenantContext::reset();
        });

        Blade::directive('canRoute', function (string $expression): string {
            return "<?php if (\App\Support\RoutePermissionMap::allows({$expression})): ?>";
        });

        Blade::directive('endCanRoute', function (): string {
            return '<?php endif; ?>';
        });

        Blade::directive('npr', function (string $expression): string {
            return "<?php echo \App\Support\NepaliNumber::formatNpr({$expression}); ?>";
        });

        Blade::directive('bs', function (string $expression): string {
            return "<?php echo \App\Support\BikramSambat::formatBs({$expression}); ?>";
        });

        Blade::directive('devanagari', function (string $expression): string {
            return "<?php echo \App\Support\NepaliNumber::toDevanagari({$expression}); ?>";
        });

        Blade::directive('qty', function (string $expression): string {
            return "<?php echo \App\Support\NepaliNumber::formatQuantity({$expression}); ?>";
        });

        Blade::directive('quantity', function (string $expression): string {
            return "<?php echo \App\Support\NepaliNumber::formatQuantity({$expression}); ?>";
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
