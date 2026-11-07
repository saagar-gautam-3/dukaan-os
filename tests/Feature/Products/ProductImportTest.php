<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductImportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    protected function createShopWithOwner(): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);

        $user = User::factory()->create();
        ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles['Shop Owner']->id,
            'is_active' => true,
        ]);

        return [$shop, $user, $roles['Shop Owner']];
    }

    public function test_template_download_has_bom_and_header(): void
    {
        [$shop, $user] = $this->createShopWithOwner();

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.import.template'));

        $response->assertStatus(200);
        $this->assertStringStartsWith("\xEF\xBB\xBF", $response->getContent());
    }
}
