<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopSwitchingTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_user_with_multiple_shops_can_view_picker(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::factory()->create(['name' => 'First Kirana']);
        $shopB = Shop::factory()->create(['name' => 'Second Store']);

        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopUser::factory()->create([
            'shop_id' => $shopB->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->get('/shops/picker');

        $response->assertStatus(200);
        $response->assertSee('First Kirana');
        $response->assertSee('Second Store');
    }

    public function test_user_can_switch_active_shop(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::factory()->create(['name' => 'Shop Alpha']);
        $shopB = Shop::factory()->create(['name' => 'Shop Beta']);

        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        ShopUser::factory()->create([
            'shop_id' => $shopB->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shopA->id])
            ->post("/shops/switch/{$shopB->id}");

        $response->assertRedirect(route('dashboard'));
        $this->assertEquals($shopB->id, session('current_shop_id'));
    }

    public function test_user_cannot_switch_to_unauthorized_shop(): void
    {
        $user = User::factory()->create();
        $shopA = Shop::factory()->create(['name' => 'My Shop']);
        $shopOther = Shop::factory()->create(['name' => 'Stranger Shop']);

        ShopUser::factory()->create([
            'shop_id' => $shopA->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // Attempting to switch to a shop the user has no membership in
        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shopA->id])
            ->post("/shops/switch/{$shopOther->id}");

        $response->assertStatus(403);
    }

    public function test_user_cannot_switch_to_deactivated_shop(): void
    {
        $user = User::factory()->create();
        $shopInactive = Shop::factory()->inactive()->create(['name' => 'Closed Shop']);

        ShopUser::factory()->create([
            'shop_id' => $shopInactive->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->post("/shops/switch/{$shopInactive->id}");

        $response->assertStatus(403);
    }

    public function test_removed_staff_membership_immediately_loses_access(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $membership = ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        // User initially has access to dashboard
        $response1 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get('/dashboard');

        $response1->assertStatus(200);

        // Staff is removed: membership row deleted
        $membership->delete();

        // On the very next request, access is immediately blocked with 403
        $response2 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get('/dashboard');

        $response2->assertStatus(403);
    }

    public function test_role_change_immediately_updates_permission_access(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);

        $cashierRole = $roles['Cashier'];
        $ownerRole = $roles['Shop Owner'];

        // Assign user as Cashier (does not have shops.manage permission)
        $membership = ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        // Attempting to access shop settings as Cashier is unauthorized (403)
        $response1 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get('/settings/shop');

        $response1->assertStatus(403);

        // Promote user to Shop Owner (has shops.manage permission)
        $membership->update(['role_id' => $ownerRole->id]);

        // Immediately has access on next request
        $response2 = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get('/settings/shop');

        $response2->assertStatus(200);
        $response2->assertSee($shop->name);
    }
}
