<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PlatformListTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return $this->actingAsPlatformAdmin();
    }

    public function test_search_by_name_slug_and_owner_email(): void
    {
        $admin = $this->admin();
        $shop = Shop::factory()->create(['name' => 'Findable Shop', 'slug' => 'findable-shop']);
        TenantContext::setShop($shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(['email' => 'owner@findable.test']);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        TenantContext::reset();

        $this->actingAsPlatformAdmin($admin);
        $this->get(route('platform.shops.index', ['q' => 'Findable']))->assertSee('Findable Shop');
        $this->get(route('platform.shops.index', ['q' => 'findable-shop']))->assertSee('Findable Shop');
        $this->get(route('platform.shops.index', ['q' => 'owner@findable.test']))->assertSee('Findable Shop');
    }

    public function test_status_filter(): void
    {
        $admin = $this->admin();
        Shop::factory()->create(['name' => 'Active Shop', 'is_active' => true]);
        Shop::factory()->create(['name' => 'Suspended Shop', 'is_active' => false]);

        $this->actingAsPlatformAdmin($admin);
        $this->get(route('platform.shops.index', ['status' => 'active']))->assertSee('Active Shop')->assertDontSee('Suspended Shop');
        $this->get(route('platform.shops.index', ['status' => 'suspended']))->assertSee('Suspended Shop')->assertDontSee('Active Shop');
    }

    public function test_pagination_keeps_query_string(): void
    {
        $admin = $this->admin();
        foreach (range(1, 30) as $i) {
            Shop::factory()->create(['name' => "Shop {$i}"]);
        }

        $this->actingAsPlatformAdmin($admin);
        $response = $this->get(route('platform.shops.index', ['q' => 'Shop', 'page' => 2]));
        $response->assertOk();
        $this->assertStringContainsString('q=Shop', $response->getContent());
        $this->assertStringContainsString('page=1', $response->getContent());
    }

    public function test_no_n_plus_one(): void
    {
        $admin = $this->admin();
        foreach (range(1, 5) as $i) {
            $shop = Shop::factory()->create(['name' => "Shop {$i}"]);
            TenantContext::setShop($shop);
            $rbac = app(RbacService::class);
            $roles = $rbac->createDefaultRolesForShop($shop);
            $owner = User::factory()->create();
            ShopUser::factory()->create([
                'shop_id' => $shop->id,
                'user_id' => $owner->id,
                'role_id' => $roles['Shop Owner']->id,
                'status' => 'active',
            ]);
            TenantContext::reset();
        }

        $this->actingAsPlatformAdmin($admin);
        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $this->get(route('platform.shops.index'))->assertOk();
        $this->assertLessThanOrEqual(8, $queries);
    }

    public function test_empty_state(): void
    {
        $admin = $this->admin();
        $this->actingAsPlatformAdmin($admin);
        $this->get(route('platform.shops.index', ['q' => 'zzzz-no-match']))
            ->assertOk()
            ->assertSee(__('platform.empty'));
    }
}
