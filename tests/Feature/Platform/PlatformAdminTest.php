<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_shop_owner_denied_platform_routes(): void
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        $this->actingAs($user);
        TenantContext::setShop($shop);

        $this->get('/platform/shops')->assertStatus(403);
    }

    public function test_platform_admin_allowed_and_toggle_writes_audit(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Shop::factory()->create(['is_active' => true]);
        $this->actingAs($admin);

        $before = AuditLog::count();
        $response = $this->get('/platform/shops');
        $response->assertOk();
        $this->assertStringNotContainsString('sales', $response->getContent());

        $this->post(route('platform.shops.toggle', $shop))->assertRedirect();
        $this->assertSame($before + 1, AuditLog::count());
        $this->assertFalse($shop->fresh()->is_active);

        // reactivation restores access
        $this->post(route('platform.shops.toggle', $shop))->assertRedirect();
        $this->assertTrue($shop->fresh()->is_active);
    }

    public function test_platform_index_exposes_counts_only(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        Shop::factory()->create(['name' => 'Secret Shop']);
        $this->actingAs($admin);

        $response = $this->get(route('platform.shops.index'));
        $response->assertOk();
        $response->assertSee('Secret Shop');
        $response->assertDontSee('sales_total');
        $response->assertDontSee('customer_email');
    }

    public function test_platform_create_admin_command(): void
    {
        $this->artisan('platform:create-admin', [
            '--name' => 'Operator',
            '--email' => 'operator@example.com',
        ])->expectsQuestion('Password', 'secret123')
            ->assertExitCode(0);

        $user = User::where('email', 'operator@example.com')->first();
        $this->assertNotNull($user);
        $this->assertTrue($user->is_platform_admin);
    }

    public function test_deactivated_shop_blocks_users(): void
    {
        $admin = User::factory()->create(['is_platform_admin' => true]);
        $shop = Shop::factory()->create(['is_active' => true]);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->actingAs($admin);
        $this->post(route('platform.shops.toggle', $shop));

        $this->actingAs($user);
        TenantContext::setShop($shop);
        $response = $this->get(route('dashboard'));
        $this->assertTrue(in_array($response->getStatusCode(), [302, 403]));
    }
}
