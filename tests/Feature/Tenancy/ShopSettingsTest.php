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

class ShopSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_shop_settings_can_be_updated_by_owner(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create([
            'name' => 'Original Shop Name',
            'business_type' => 'kirana',
            'timezone' => 'Asia/Kathmandu',
        ]);

        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $ownerRole = $roles['Shop Owner'];

        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->put('/settings/shop', [
                'name' => 'Updated Shop Name',
                'business_type' => 'clothing',
                'timezone' => 'Asia/Kathmandu',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('status');

        $this->assertDatabaseHas('shops', [
            'id' => $shop->id,
            'name' => 'Updated Shop Name',
            'business_type' => 'clothing',
        ]);
    }

    public function test_shop_settings_update_fails_for_invalid_timezone(): void
    {
        $user = User::factory()->create();
        $shop = Shop::factory()->create();

        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $ownerRole = $roles['Shop Owner'];

        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $ownerRole->id,
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->put('/settings/shop', [
                'name' => 'Invalid Timezone Store',
                'business_type' => 'kirana',
                'timezone' => 'Invalid/Timezone',
            ]);

        $response->assertSessionHasErrors('timezone');
    }
}
