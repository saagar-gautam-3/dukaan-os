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

        // Staff is removed: membership row deleted and all of the user's sessions become stale.
        $membership->delete();
        $this->assertSame(1, (int) $user->fresh()->permissions_version);

        $response2 = $this->actingAs($user->fresh())
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => 0])
            ->get('/dashboard');

        $response2->assertRedirect(route('login'));
    }

    public function test_role_change_invalidates_existing_user_session(): void
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

        // Promotion invalidates the existing session so it cannot retain stale permissions.
        $membership->update(['role_id' => $ownerRole->id]);

        $this->assertSame(1, (int) $user->fresh()->permissions_version);

        $freshSessionResponse = $this->actingAs($user->fresh())
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => 1])
            ->get('/settings/shop');

        $freshSessionResponse->assertOk();
        $freshSessionResponse->assertSee($shop->name);

        $response2 = $this->actingAs($user->fresh())
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => 0])
            ->get('/settings/shop');

        $response2->assertRedirect(route('login'));
    }

    public function test_role_demotion_denies_permission_with_fresh_session(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $membership = ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => 0])
            ->get('/settings/shop')
            ->assertOk();

        $membership->update(['role_id' => $roles['Cashier']->id]);
        $currentVersion = (int) $user->fresh()->permissions_version;
        $this->assertSame(1, $currentVersion);

        $this->actingAs($user->fresh())
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => $currentVersion])
            ->get('/settings/shop')
            ->assertForbidden();
    }

    public function test_membership_deactivation_invalidates_existing_user_session(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();
        $membership = ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get('/dashboard')
            ->assertOk();

        $membership->update(['status' => 'inactive']);
        $this->assertSame(1, (int) $user->fresh()->permissions_version);

        $this->actingAs($user->fresh())
            ->withSession(['current_shop_id' => $shop->id, 'permissions_version' => 0])
            ->get('/dashboard')
            ->assertRedirect(route('login'));
    }
}
