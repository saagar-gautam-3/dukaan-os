<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Tenancy\TenantContext;
use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Throwaway tenant-owned test model.
 */
class TestTenantItem extends Model
{
    use Auditable, BelongsToShop;

    protected $table = 'test_tenant_items';

    protected $fillable = [
        'name',
        'price',
    ];
}

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Create throwaway tenant-owned table
        Schema::create('test_tenant_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2)->default(0.00);
            $table->timestamps();
        });

        // Register test routes protected by tenancy middleware
        Route::middleware(['web', 'auth', 'tenant.resolve', 'shop.access'])->group(function () {
            Route::get('/test-tenant/items/{item}', function (TestTenantItem $item) {
                return response()->json([
                    'id' => $item->id,
                    'name' => $item->name,
                ]);
            })->name('test-tenant.items.show');

            Route::put('/test-tenant/items/{item}', function (Request $request, TestTenantItem $item) {
                $item->update($request->only('name', 'price'));

                return response()->json([
                    'id' => $item->id,
                    'name' => $item->name,
                ]);
            })->name('test-tenant.items.update');

            Route::delete('/test-tenant/items/{item}', function (TestTenantItem $item) {
                $item->delete();

                return response()->json(['deleted' => true]);
            })->name('test-tenant.items.destroy');
        });
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        Schema::dropIfExists('test_tenant_items');

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
        $itemB = new TestTenantItem();
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'Shop B Secret Item';
        $itemB->price = 100.00;
        $itemB->save();

        // User A accesses Shop B's item ID under Shop A's session
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

        $itemB = new TestTenantItem();
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'Original Name';
        $itemB->price = 100.00;
        $itemB->save();

        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->putJson("/test-tenant/items/{$itemB->id}", [
                'name' => 'Malicious Update',
            ]);

        $response->assertNotFound();
        $this->assertDatabaseHas('test_tenant_items', [
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

        $itemB = new TestTenantItem();
        $itemB->shop_id = $shopB->id;
        $itemB->name = 'To Not Delete';
        $itemB->price = 50.00;
        $itemB->save();

        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->deleteJson("/test-tenant/items/{$itemB->id}");

        $response->assertNotFound();
        $this->assertDatabaseHas('test_tenant_items', [
            'id' => $itemB->id,
        ]);
    }

    public function test_belongs_to_shop_auto_fills_shop_id_and_ignores_user_input(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        TenantContext::setShop($shopA);

        // Attempt to pass shop_id for Shop B via mass assignment
        $item = TestTenantItem::create([
            'name' => 'Tenant Item 1',
            'price' => 25.50,
            'shop_id' => $shopB->id,
        ]);

        // Invariant: shop_id is always filled from TenantContext, never user input
        $this->assertSame($shopA->id, $item->shop_id);
        $this->assertDatabaseHas('test_tenant_items', [
            'id' => $item->id,
            'shop_id' => $shopA->id,
        ]);
    }

    public function test_global_scope_isolates_records_between_tenants(): void
    {
        $shopA = Shop::factory()->create();
        $shopB = Shop::factory()->create();

        TenantContext::setShop($shopA);
        $itemA = TestTenantItem::create(['name' => 'Item A', 'price' => 10]);

        TenantContext::setShop($shopB);
        $itemB = TestTenantItem::create(['name' => 'Item B', 'price' => 20]);

        // Active context is Shop B: cannot see Shop A's item
        $itemsInB = TestTenantItem::all();
        $this->assertCount(1, $itemsInB);
        $this->assertTrue($itemsInB->contains('id', $itemB->id));
        $this->assertFalse($itemsInB->contains('id', $itemA->id));

        // Switch to Shop A: cannot see Shop B's item
        TenantContext::setShop($shopA);
        $itemsInA = TestTenantItem::all();
        $this->assertCount(1, $itemsInA);
        $this->assertTrue($itemsInA->contains('id', $itemA->id));
        $this->assertFalse($itemsInA->contains('id', $itemB->id));
    }

    public function test_missing_tenant_context_aborts_safely(): void
    {
        $userWithoutShops = User::factory()->create();

        // No session shop_id and user has no shops
        $response = $this->actingAs($userWithoutShops)
            ->getJson('/test-tenant/items/1');

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
            ->getJson('/test-tenant/items/1');

        $response->assertStatus(403);
    }

    public function test_removed_membership_loses_access(): void
    {
        $shop = Shop::factory()->create();
        $user = User::factory()->create();

        // User is not associated with shop in shop_user table
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->getJson('/test-tenant/items/1');

        $response->assertStatus(403);
    }

    public function test_audit_log_records_master_data_changes(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $this->actingAs($user);
        TenantContext::setShop($shop);

        // Created event
        $item = TestTenantItem::create([
            'name' => 'Audited Item',
            'price' => 125.00,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'created',
            'auditable_type' => TestTenantItem::class,
            'auditable_id' => $item->id,
        ]);

        // Updated event
        $item->update(['price' => 150.00]);

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'updated',
            'auditable_type' => TestTenantItem::class,
            'auditable_id' => $item->id,
        ]);

        // Deleted event
        $itemId = $item->id;
        $item->delete();

        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'event' => 'deleted',
            'auditable_type' => TestTenantItem::class,
            'auditable_id' => $itemId,
        ]);
    }
}
