<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ShopPickerShopCreationTest extends TestCase
{
    use RefreshDatabase;

    public function test_verified_user_without_memberships_can_register_shop_from_picker_and_reach_dashboard(): void
    {
        $user = User::factory()->create([
            'email' => 'shop-picker-owner@example.com',
            'email_verified_at' => now(),
            'is_platform_admin' => true,
        ]);

        $response = $this->actingAs($user)->followingRedirects()->post(route('shops.store'), [
            'name' => 'Picker Created Shop',
            'business_type' => 'kirana',
        ]);

        $response->assertOk();
        $shop = Shop::query()->where('name', 'Picker Created Shop')->firstOrFail();
        $this->assertDatabaseHas('shop_user', [
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => Role::query()->where('shop_id', $shop->id)->where('name', 'Shop Owner')->value('id'),
            'status' => 'active',
        ]);
        $this->assertSame($shop->id, session('current_shop_id'));
        $this->assertSame(5, Role::query()->where('shop_id', $shop->id)->count());
        $this->assertSame(1, AuditLog::query()
            ->where('auditable_type', $shop->getMorphClass())
            ->where('auditable_id', $shop->id)
            ->where('event', 'created')
            ->count());
    }

    public function test_existing_member_can_create_second_shop_and_new_shop_becomes_active(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $oldShop = Shop::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $oldShop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        app(RbacService::class)->createDefaultRolesForShop($oldShop);
        $oldRoles = Role::withoutGlobalScopes()->where('shop_id', $oldShop->id)
            ->orderBy('id')->get(['id', 'name', 'shop_id'])->toArray();
        $this->assertCount(5, $oldRoles);
        TenantContext::setShop($oldShop);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $oldShop->id])
            ->post(route('shops.store'), [
                'name' => 'Second Shop',
                'business_type' => 'general',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status', __('shops.created_success'));
        $response->assertSessionMissing('error');
        $newShop = Shop::query()->where('name', 'Second Shop')->firstOrFail();
        $this->assertNotSame($oldShop->id, $newShop->id);
        $this->assertSame($newShop->id, session('current_shop_id'));
        $this->assertDatabaseHas('shop_user', [
            'shop_id' => $newShop->id,
            'user_id' => $user->id,
            'status' => 'active',
        ]);
        $expectedRoleNames = ['Shop Owner', 'Manager', 'Cashier', 'Inventory Staff', 'Accountant'];
        $newRoles = Role::withoutGlobalScopes()->where('shop_id', $newShop->id)->get();
        $this->assertSame(5, $newRoles->count());
        $this->assertEqualsCanonicalizing($expectedRoleNames, $newRoles->pluck('name')->all());
        $this->assertSame([$newShop->id], $newRoles->pluck('shop_id')->unique()->values()->all());
        $this->assertSame($oldRoles, Role::withoutGlobalScopes()->where('shop_id', $oldShop->id)
            ->orderBy('id')->get(['id', 'name', 'shop_id'])->toArray());
        $this->assertSame(5, $newRoles->pluck('name')->unique()->count());
        TenantContext::reset();
    }

    public function test_tenant_resolve_403_uses_nepali_message(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        config(['tenancy.on_missing_context' => 'abort_403']);
        app()->setLocale('ne');

        $this->actingAs($user)
            ->get('/dashboard')
            ->assertForbidden()
            ->assertSee(__('errors.tenant_context_missing'));
    }
}
