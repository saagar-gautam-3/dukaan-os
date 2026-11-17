<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_routes_require_auth_verified_and_platform_admin(): void
    {
        $shop = Shop::factory()->create(['is_active' => true]);

        // anonymous
        $this->get('/platform/shops')->assertRedirect('/login');
        $this->get('/platform/shops/'.$shop->id.'/suspend')->assertRedirect('/login');

        // shop owner
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(['email_verified_at' => now()]);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::reset();
        $this->actingAs($owner);
        $this->get('/platform/shops')->assertStatus(403);

        // unverified platform admin
        $unverified = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => null]);
        $this->actingAs($unverified);
        $this->get('/platform/shops')->assertRedirect(route('verification.notice'));

        // verified platform admin
        $admin = User::factory()->create(['is_platform_admin' => true, 'email_verified_at' => now()]);
        $this->actingAs($admin);
        $this->get('/platform/shops')->assertOk();
        $this->get('/platform/shops/'.$shop->id.'/suspend')->assertOk();
    }
}
