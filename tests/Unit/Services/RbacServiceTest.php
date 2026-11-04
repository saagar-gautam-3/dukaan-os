<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Role;
use App\Models\Shop;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RbacServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_default_roles_are_created_for_requested_shop_when_another_shop_is_active(): void
    {
        $activeShop = Shop::factory()->create();
        $targetShop = Shop::factory()->create();
        TenantContext::setShop($activeShop);

        app(RbacService::class)->createDefaultRolesForShop($targetShop);

        $roles = Role::withoutGlobalScopes()->where('shop_id', $targetShop->id)->get();
        $this->assertSame(5, $roles->count());
        $this->assertSame(5, $roles->pluck('name')->unique()->count());
        $this->assertSame([$targetShop->id], $roles->pluck('shop_id')->unique()->values()->all());
        $this->assertSame($activeShop->id, TenantContext::getShopId());
    }
}
