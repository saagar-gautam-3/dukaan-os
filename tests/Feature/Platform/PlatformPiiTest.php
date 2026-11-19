<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Customer;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlatformPiiTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_pages_do_not_expose_tenant_pii(): void
    {
        $this->actingAsPlatformAdmin();
        $shop = Shop::factory()->create(['name' => 'PII Shop', 'is_active' => true]);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(['phone' => '9800000001', 'email' => 'owner@pii.test']);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        $customer = Customer::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Distinctive Customer Name',
            'phone' => '9811111111',
        ]);
        TenantContext::reset();

        $secretStrings = ['Distinctive Customer Name', '9811111111', '9800000001'];

        foreach (['/platform/shops', '/platform/shops/'.$shop->id.'/suspend'] as $url) {
            $response = $this->get($url);
            $response->assertOk();
            foreach ($secretStrings as $secret) {
                $this->assertStringNotContainsString($secret, $response->getContent());
            }
        }
    }

    public function test_suspension_reason_not_shown_to_shop_users(): void
    {
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
        TenantContext::reset();

        $this->actingAsPlatformAdmin();
        $this->post(route('platform.shops.suspend.store', $shop), ['reason' => 'SECRET-REASON-123'])
            ->assertRedirect();

        $this->actingAs($user);
        TenantContext::setShop($shop);
        $response = $this->get(route('dashboard'));
        $this->assertStringNotContainsString('SECRET-REASON-123', $response->getContent());
    }
}
