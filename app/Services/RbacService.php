<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Permission;
use App\Models\Role;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Collection;

class RbacService
{
    /**
     * Complete list of permissions for Dukaan OS.
     *
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'shops.manage' => 'Manage shop profile and settings',
        'users.view' => 'View shop staff members',
        'users.manage' => 'Add, edit, or remove staff members',
        'staff.manage' => 'Manage staff members and their roles',
        'roles.view' => 'View roles and permissions',
        'roles.manage' => 'Manage custom roles and assignments',
        'products.view' => 'View products and catalog',
        'products.manage' => 'Add, edit, or remove products',
        'inventory.view' => 'View stock balances',
        'inventory.manage' => 'Adjust stock and manage inventory movements',
        'sales.view' => 'View sales and invoices',
        'sales.create' => 'Create POS sales and bills',
        'sales.cancel' => 'Cancel or refund sales',
        'sales.return' => 'Process sale returns',
        'purchases.view' => 'View purchases',
        'purchases.manage' => 'Create and edit purchases',
        'customers.view' => 'View customers and ledgers (Udhar Khata)',
        'customers.manage' => 'Manage customers and record payments',
        'udhar.view' => 'View udhar khata and customer ledgers',
        'udhar.collect' => 'Record customer payment collection',
        'udhar.remind' => 'Send payment reminders to customers',
        'suppliers.view' => 'View suppliers and ledgers',
        'suppliers.manage' => 'Manage suppliers and record payments',
        'expenses.view' => 'View expenses',
        'expenses.create' => 'Record expenses',
        'expenses.manage' => 'Record and manage expenses and categories',
        'expenses.delete' => 'Delete expenses',
        'cash.view' => 'View cash sessions and drawer history',
        'payments.verify' => 'Verify or fail pending QR payments',
        'cash.manage' => 'Open, close, and manage cash sessions',
        'reports.view' => 'View operational reports',
        'reports.profit.view' => 'View gross and net profit reports',
        'reports.export' => 'Export reports as CSV or PDF',
        'audit.view' => 'View audit logs',
    ];

    /**
     * Ensure all system permissions exist in database.
     *
     * @return Collection<int, Permission>
     */
    public function syncSystemPermissions()
    {
        $permissions = [];
        foreach (array_keys(self::PERMISSIONS) as $name) {
            $permissions[] = Permission::firstOrCreate(
                ['name' => $name],
                ['guard_name' => 'web']
            );
        }

        return Permission::all();
    }

    /**
     * Create default roles for a given shop and assign standard permissions.
     *
     * @return array<string, Role>
     */
    public function createDefaultRolesForShop(Shop $shop): array
    {
        return TenantContext::runAs($shop, function () use ($shop): array {
            $this->syncSystemPermissions();
            $allPermissions = Permission::all()->keyBy('name');

            $roleDefinitions = [
                'Shop Owner' => array_keys(self::PERMISSIONS),
                'Manager' => [
                    'users.view', 'products.view', 'products.manage',
                    'inventory.view', 'inventory.manage', 'sales.view',
                    'sales.create', 'sales.cancel', 'sales.return', 'purchases.view',
                    'purchases.manage', 'customers.view', 'customers.manage',
                    'udhar.view', 'udhar.collect', 'udhar.remind',
                    'suppliers.view', 'suppliers.manage', 'expenses.view',
                    'expenses.create', 'expenses.manage', 'expenses.delete',
                    'cash.view', 'cash.manage', 'reports.view', 'reports.profit.view',
                    'reports.export', 'audit.view',
                ],
                'Cashier' => [
                    'products.view', 'sales.view', 'sales.create', 'sales.return',
                    'customers.view', 'customers.manage', 'udhar.view', 'udhar.collect',
                    'cash.view', 'cash.manage',
                ],
                'Inventory Staff' => [
                    'products.view', 'products.manage',
                    'inventory.view', 'inventory.manage',
                    'suppliers.view', 'purchases.view', 'purchases.manage',
                ],
                'Accountant' => [
                    'sales.view', 'purchases.view', 'expenses.view',
                    'expenses.create', 'expenses.manage', 'customers.view',
                    'suppliers.view', 'udhar.view', 'cash.view',
                    'reports.view', 'reports.profit.view', 'reports.export',
                ],
            ];

            $roles = [];

            foreach ($roleDefinitions as $roleName => $permissionNames) {
                /** @var Role|null $role */
                $role = Role::withoutGlobalScopes()
                    ->where('shop_id', $shop->id)
                    ->where('name', $roleName)
                    ->first();

                if ($role === null) {
                    $role = new Role;
                    $role->shop_id = $shop->id;
                    $role->name = $roleName;
                    $role->guard_name = 'web';
                    $role->save();
                }

                $permissionIds = collect($permissionNames)
                    ->map(fn (string $name) => $allPermissions->get($name)?->id)
                    ->filter()
                    ->values()
                    ->all();

                $role->permissions()->sync($permissionIds);
                $roles[$roleName] = $role;
            }

            return $roles;
        });
    }
}
