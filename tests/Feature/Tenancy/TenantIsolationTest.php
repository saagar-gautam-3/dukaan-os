<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Register test routes protected by tenancy middleware using real Customer tenant model
        Route::middleware(['web', 'auth', 'tenant.resolve', 'shop.access'])->group(function () {
            Route::get('/test-tenant/dashboard', function () {
                return response()->json(['ok' => true]);
            })->name('test-tenant.dashboard');

            Route::get('/test-tenant/items/{customer}', function (Customer $customer) {
                return response()->json([
                    'id' => $customer->id,
                    'name' => $customer->name,
                ]);
            })->name('test-tenant.items.show');

            Route::put('/test-tenant/items/{customer}', function (Request $request, Customer $customer) {
                $customer->update($request->only('name', 'phone'));

                return response()->json([
                    'id' => $customer->id,
                    'name' => $customer->name,
                ]);
            })->name('test-tenant.items.update');

            Route::delete('/test-tenant/items/{customer}', function (Customer $customer) {
                $customer->delete();

                return response()->json(['deleted' => true]);
            })->name('test-tenant.items.destroy');
        });
    }

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_cross_tenant_read_denied_via_route_model_binding(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);

        $userA = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        // Create an item owned by Shop B
        $itemB = new Customer;
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'Shop B Secret Customer';
        $itemB->save();

        // User A accesses Shop B's customer ID under Shop A's session
        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->getJson("/test-tenant/items/{$itemB->id}");

        // Tenant-scoped route-model binding must return 404
        $response->assertNotFound();
    }

    public function test_cross_tenant_update_denied_via_route_model_binding(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);

        $userA = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        $itemB = new Customer;
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'Original Name';
        $itemB->save();

        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->putJson("/test-tenant/items/{$itemB->id}", [
                'name' => 'Malicious Update',
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('customers', [
            'id' => $itemB->id,
            'name' => 'Original Name',
        ]);
    }

    public function test_cross_tenant_delete_denied_via_route_model_binding(): void
    {
        $shopA = Shop::factory()->create(['name' => 'Shop A']);
        $shopB = Shop::factory()->create(['name' => 'Shop B']);

        $userA = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $userA->id,
            'status' => 'active',
        ]);

        $itemB = new Customer;
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'To Not Delete';
        $itemB->save();

        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->deleteJson("/test-tenant/items/{$itemB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('customers', [
            'id' => $itemB->id,
        ]);
    }

    public function test_belongs_to_shop_auto_fills_shop_id_and_ignores_user_input(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        TenantContext::setShop($shopA);

        // Attempt to pass shop_id for Shop B via mass assignment
        $item = Customer::create([
            'name' => 'Tenant Item 1',
            'shop_id' => $shopB->id,
        ]);

        // Invariant: shop_id is always filled from TenantContext, never user input
        $this->assertSame($shopA->id, $item->shop_id);
        $this->assertDatabaseHas('customers', [
            'id' => $item->id,
            'shop_id' => $shopA->id,
        ]);
    }

    public function test_global_scope_isolates_records_between_tenants(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        TenantContext::setShop($shopA);
        $itemA = Customer::create(['name' => 'Item A']);

        TenantContext::setShop($shopB);
        $itemB = Customer::create(['name' => 'Item B']);

        // Active context is Shop B: cannot see Shop A's item
        $itemsInB = Customer::all();
        $this->assertCount(1, $itemsInB);
        $this->assertTrue($itemsInB->contains('id', $itemB->id));
        $this->assertFalse($itemsInB->contains('id', $itemA->id));

        // Switch to Shop A: cannot see Shop B's item
        TenantContext::setShop($shopA);
        $itemsInA = Customer::all();
        $this->assertCount(1, $itemsInA);
        $this->assertTrue($itemsInA->contains('id', $itemA->id));
        $this->assertFalse($itemsInA->contains('id', $itemB->id));
    }

    public function test_missing_tenant_context_aborts_safely(): void
    {
        $userWithoutShops = User::factory()->create();

        // No session shop_id and user has no shops
        $response = $this->actingAs($userWithoutShops)
            ->getJson('/test-tenant/dashboard');

        $response->assertStatus(403);
    }

    public function test_inactive_membership_loses_access(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();

        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'inactive',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->getJson('/test-tenant/dashboard');

        $response->assertStatus(403);
    }

    public function test_removed_membership_loses_access(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();

        // User is not associated with shop in shop_user table
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->getJson('/test-tenant/dashboard');

        $response->assertStatus(403);
    }

    public function test_audit_log_records_master_data_changes(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $this->actingAs($user);
        TenantContext::setShop($shop);

        // Created event
        $item = Customer::create([
            'name' => 'Audited Item',
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'created',
            'auditable_type' => Customer::class,
            'auditable_id' => $item->id,
        ]);

        // Updated event
        $item->update(['name' => 'Updated Audited Item']);

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'updated',
            'auditable_type' => Customer::class,
            'auditable_id' => $item->id,
        ]);

        // Deleted event
        $itemId = $item->id;
        $item->delete();

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'deleted',
            'auditable_type' => Customer::class,
            'auditable_id' => $itemId,
        ]);
    }
}
