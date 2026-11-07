<?php

declare(strict_types=1);

namespace App\Support;

use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\Gate;

/**
 * Single source of truth for the permission each named tenant route needs.
 *
 * Controllers stay authoritative: they still call authorize()/abort_unless().
 * This map mirrors those checks so UI code (via can_route()/@canRoute) hides
 * actions a user cannot perform, and so tests can compare routes to roles.
 */
final class RoutePermissionMap
{
    /**
     * @var array<string, array{
     *     permissions?: list<string>,
     *     all_permissions?: list<string>,
     *     ability?: string,
     *     platform?: bool,
     *     member?: bool,
     *     authenticated?: bool
     * }>
     */
    private const ROUTES = [
        // Dashboard: any active member of the current shop.
        'dashboard' => ['member' => true],

        // Cash drawer.
        'cash.index' => ['permissions' => ['cash.view']],
        'cash.show' => ['permissions' => ['cash.view'], 'ability' => 'view'],
        'cash.open' => ['permissions' => ['cash.manage']],
        'cash.close' => ['permissions' => ['cash.manage'], 'ability' => 'close'],

        // Catalog.
        'categories.index' => ['permissions' => ['products.view']],
        'categories.store' => ['permissions' => ['products.manage']],
        'categories.update' => ['permissions' => ['products.manage'], 'ability' => 'update'],
        'categories.destroy' => ['permissions' => ['products.manage'], 'ability' => 'delete'],
        'products.index' => ['permissions' => ['products.view']],
        'products.create' => ['permissions' => ['products.manage']],
        'products.store' => ['permissions' => ['products.manage']],
        'products.edit' => ['permissions' => ['products.manage'], 'ability' => 'update'],
        'products.update' => ['permissions' => ['products.manage'], 'ability' => 'update'],
        'products.destroy' => ['permissions' => ['products.manage'], 'ability' => 'delete'],
        'units.index' => ['permissions' => ['products.view']],

        // Product import requires both catalog and inventory rights.
        'products.import.create' => ['all_permissions' => ['products.manage', 'inventory.manage']],
        'products.import.store' => ['all_permissions' => ['products.manage', 'inventory.manage']],
        'products.import.template' => ['all_permissions' => ['products.manage', 'inventory.manage']],
        'products.import.template.xlsx' => ['all_permissions' => ['products.manage', 'inventory.manage']],
        'products.import.confirm' => ['all_permissions' => ['products.manage', 'inventory.manage'], 'ability' => 'update'],
        'products.import.preview' => ['all_permissions' => ['products.manage', 'inventory.manage'], 'ability' => 'view'],
        'products.import.success' => ['all_permissions' => ['products.manage', 'inventory.manage'], 'ability' => 'view'],
        'products.import.errors' => ['all_permissions' => ['products.manage', 'inventory.manage'], 'ability' => 'view'],
        'products.import.errors.xlsx' => ['all_permissions' => ['products.manage', 'inventory.manage'], 'ability' => 'view'],

        // Customers.
        'customers.index' => ['permissions' => ['customers.view']],
        'customers.create' => ['permissions' => ['customers.manage']],
        'customers.store' => ['permissions' => ['customers.manage']],
        'customers.show' => ['permissions' => ['customers.view'], 'ability' => 'view'],
        'customers.edit' => ['permissions' => ['customers.manage'], 'ability' => 'update'],
        'customers.update' => ['permissions' => ['customers.manage'], 'ability' => 'update'],
        'customers.destroy' => ['permissions' => ['customers.manage'], 'ability' => 'delete'],

        // Expense categories and expenses.
        'expense-categories.index' => ['permissions' => ['expenses.view']],
        'expense-categories.create' => ['permissions' => ['expenses.manage']],
        'expense-categories.store' => ['permissions' => ['expenses.manage']],
        'expense-categories.edit' => ['permissions' => ['expenses.manage'], 'ability' => 'update'],
        'expense-categories.update' => ['permissions' => ['expenses.manage'], 'ability' => 'update'],
        'expense-categories.destroy' => ['permissions' => ['expenses.manage'], 'ability' => 'delete'],
        'expenses.index' => ['permissions' => ['expenses.view']],
        'expenses.create' => ['permissions' => ['expenses.create', 'expenses.manage']],
        'expenses.store' => ['permissions' => ['expenses.create', 'expenses.manage']],
        'expenses.edit' => ['permissions' => ['expenses.manage'], 'ability' => 'update'],
        'expenses.update' => ['permissions' => ['expenses.manage'], 'ability' => 'update'],
        'expenses.destroy' => ['permissions' => ['expenses.delete', 'expenses.manage'], 'ability' => 'delete'],

        // Payments.
        'payments.verify' => ['permissions' => ['payments.verify']],
        'payments.fail' => ['permissions' => ['payments.verify']],

        // POS and sales.
        'pos' => ['permissions' => ['sales.create']],
        'pos.products' => ['permissions' => ['sales.create']],
        'pos.customers' => ['permissions' => ['customers.view']],
        'sales.index' => ['permissions' => ['sales.view']],
        'sales.store' => ['permissions' => ['sales.create']],
        'sales.show' => ['permissions' => ['sales.view'], 'ability' => 'view'],
        'sales.invoice' => ['permissions' => ['sales.view'], 'ability' => 'view'],
        'sales.thermal' => ['permissions' => ['sales.view'], 'ability' => 'view'],
        'sales.post' => ['permissions' => ['sales.create'], 'ability' => 'post'],
        'sales.cancel' => ['permissions' => ['sales.create'], 'ability' => 'delete'],
        'sales.return' => ['permissions' => ['sales.return'], 'ability' => 'return'],
        'sales.return.store' => ['permissions' => ['sales.return'], 'ability' => 'return'],

        // Purchases.
        'purchases.index' => ['permissions' => ['purchases.view']],
        'purchases.create' => ['permissions' => ['purchases.manage']],
        'purchases.store' => ['permissions' => ['purchases.manage']],
        'purchases.show' => ['permissions' => ['purchases.view'], 'ability' => 'view'],
        'purchases.post' => ['permissions' => ['purchases.manage'], 'ability' => 'post'],
        'purchases.destroy' => ['permissions' => ['purchases.manage'], 'ability' => 'delete'],

        // Reports.
        'reports.profit' => ['permissions' => ['reports.profit.view']],
        'reports.profit.export' => ['permissions' => ['reports.export']],
        'reports.stock' => ['permissions' => ['reports.view']],

        // Staff and settings.
        'staff.index' => ['permissions' => ['staff.manage']],
        'staff.create' => ['permissions' => ['staff.manage']],
        'staff.store' => ['permissions' => ['staff.manage']],
        'staff.edit' => ['permissions' => ['staff.manage'], 'ability' => 'update'],
        'staff.update' => ['permissions' => ['staff.manage'], 'ability' => 'update'],
        'staff.destroy' => ['permissions' => ['staff.manage'], 'ability' => 'delete'],
        'settings.shop' => ['permissions' => ['shops.manage']],
        'settings.shop.update' => ['permissions' => ['shops.manage']],

        // Shop picker/create are available to any signed-in user; switching is
        // still enforced against active memberships by the controller.
        'shops.picker' => ['authenticated' => true],
        'shops.switch' => ['authenticated' => true],
        'shops.create' => ['authenticated' => true],
        'shops.store' => ['authenticated' => true],

        // Stock.
        'stock.index' => ['permissions' => ['inventory.view']],
        'stock.alerts' => ['permissions' => ['inventory.view']],
        'stock.adjust' => ['permissions' => ['inventory.manage']],
        'stock.adjust.store' => ['permissions' => ['inventory.manage']],
        'stock.history' => ['permissions' => ['products.view'], 'ability' => 'view'],

        // Suppliers.
        'suppliers.index' => ['permissions' => ['suppliers.view']],
        'suppliers.create' => ['permissions' => ['suppliers.manage']],
        'suppliers.store' => ['permissions' => ['suppliers.manage']],
        'suppliers.edit' => ['permissions' => ['suppliers.manage'], 'ability' => 'update'],
        'suppliers.update' => ['permissions' => ['suppliers.manage'], 'ability' => 'update'],
        'suppliers.destroy' => ['permissions' => ['suppliers.manage'], 'ability' => 'delete'],

        // Udhar khata.
        'udhar.outstanding' => ['permissions' => ['udhar.view', 'customers.view']],
        'udhar.ageing' => ['permissions' => ['udhar.view', 'customers.view']],
        'udhar.collect' => ['permissions' => ['udhar.collect'], 'ability' => 'collect'],
        'udhar.collect.store' => ['permissions' => ['udhar.collect'], 'ability' => 'collect'],
        'udhar.remind' => ['permissions' => ['udhar.remind'], 'ability' => 'remind'],
        'udhar.reminders.send' => ['permissions' => ['udhar.remind'], 'ability' => 'remind'],

        // Platform operator area (never tenant scoped).
        'platform.shops.index' => ['platform' => true],
        'platform.shops.toggle' => ['platform' => true],
    ];

    /**
     * @return array<string, array{permissions?: list<string>, all_permissions?: list<string>, ability?: string, platform?: bool, member?: bool, authenticated?: bool}>
     */
    public static function all(): array
    {
        return self::ROUTES;
    }

    public static function has(string $routeName): bool
    {
        return isset(self::ROUTES[$routeName]);
    }

    /**
     * @return array{permissions?: list<string>, all_permissions?: list<string>, ability?: string, platform?: bool, member?: bool, authenticated?: bool}|null
     */
    public static function entry(string $routeName): ?array
    {
        return self::ROUTES[$routeName] ?? null;
    }

    /**
     * Check whether the current user may use the named route.
     *
     * Pass the bound model for row actions so tenant ownership and policy
     * rules are evaluated exactly like the controller does.
     */
    public static function allows(string $routeName, mixed $model = null): bool
    {
        $entry = self::ROUTES[$routeName] ?? null;
        $user = auth()->user();

        if ($entry === null || $user === null) {
            return false;
        }

        if ($entry['platform'] ?? false) {
            return $user->isPlatformAdmin();
        }

        if ($entry['authenticated'] ?? false) {
            return true;
        }

        if ($entry['member'] ?? false) {
            return TenantContext::has();
        }

        if ($model !== null && isset($entry['ability'])) {
            return Gate::forUser($user)->allows($entry['ability'], $model);
        }

        foreach ($entry['all_permissions'] ?? [] as $permission) {
            if (! $user->hasPermissionInCurrentShop($permission)) {
                return false;
            }
        }

        if (! empty($entry['all_permissions'])) {
            return true;
        }

        foreach ($entry['permissions'] ?? [] as $permission) {
            if ($user->hasPermissionInCurrentShop($permission)) {
                return true;
            }
        }

        return false;
    }
}
