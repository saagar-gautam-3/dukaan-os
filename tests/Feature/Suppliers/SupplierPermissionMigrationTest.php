<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Suppliers\Concerns\CreatesSupplierFixture;
use Tests\TestCase;

final class SupplierPermissionMigrationTest extends TestCase
{
    use CreatesSupplierFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCreatesSupplierFixture();
        parent::tearDown();
    }

    public function test_permission_is_declared_in_the_system_catalogue(): void
    {
        $this->assertArrayHasKey('suppliers.pay', RbacService::PERMISSIONS);
    }

    public function test_new_shops_receive_suppliers_pay_in_default_roles(): void
    {
        [$shop, $roles] = $this->createShopWithRoles([
            'Shop Owner',
            'Manager',
            'Accountant',
            'Cashier',
            'Inventory Staff',
        ]);

        $this->assertTrue($roles['Shop Owner']->hasPermission('suppliers.pay'));
        $this->assertTrue($roles['Manager']->hasPermission('suppliers.pay'));
        $this->assertTrue($roles['Accountant']->hasPermission('suppliers.pay'));
        $this->assertFalse($roles['Cashier']->hasPermission('suppliers.pay'));
        $this->assertFalse($roles['Inventory Staff']->hasPermission('suppliers.pay'));
    }

    public function test_existing_shop_owner_role_receives_permission_when_migration_runs(): void
    {
        [$shop, $roles, $users] = $this->createShopWithRoles([
            'Shop Owner',
            'Cashier',
            'Manager',
            'Accountant',
            'Inventory Staff',
        ]);
        $owner = $users['Shop Owner'];

        $permissionId = DB::table('permissions')->where('name', 'suppliers.pay')->value('id');
        DB::table('role_permissions')->where('permission_id', $permissionId)->delete();
        DB::table('permissions')->where('id', $permissionId)->delete();

        TenantContext::runAs($shop, function () use ($roles): void {
            $this->assertFalse($roles['Shop Owner']->fresh()->hasPermission('suppliers.pay'));
        });

        $versionBefore = (int) $owner->fresh()->permissions_version;

        $migration = require database_path('migrations/2026_11_23_000001_add_suppliers_pay_permission.php');
        $migration->up();

        TenantContext::runAs($shop, function () use ($roles, $owner, $versionBefore): void {
            $this->assertTrue($roles['Shop Owner']->fresh()->hasPermission('suppliers.pay'));
            $this->assertTrue($roles['Manager']->fresh()->hasPermission('suppliers.pay'));
            $this->assertTrue($roles['Accountant']->fresh()->hasPermission('suppliers.pay'));
            $this->assertFalse($roles['Cashier']->fresh()->hasPermission('suppliers.pay'));
            $this->assertFalse($roles['Inventory Staff']->fresh()->hasPermission('suppliers.pay'));

            $this->assertSame($versionBefore + 1, (int) $owner->fresh()->permissions_version);
        });
    }
}
