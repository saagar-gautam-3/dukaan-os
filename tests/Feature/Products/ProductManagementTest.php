<?php

declare(strict_types=1);

namespace Tests\Feature\Products;

use App\Models\Category;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    protected function createShopWithStaff(string $roleName): array
    {
        $shop = Shop::factory()->create();
        TenantContext::setShop($shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($shop);

        $user = User::factory()->create();
        $membership = ShopUser::create([
            'shop_id' => $shop->id,
            'user_id' => $user->id,
            'role_id' => $roles[$roleName]->id,
            'is_active' => true,
        ]);

        return [$shop, $user, $membership, $roles[$roleName]];
    }

    public function test_user_with_products_view_can_see_product_list(): void
    {
        [$shop, $user] = $this->createShopWithStaff('Inventory Staff');

        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'name' => 'Nepali Chiya Patti',
        ]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.index'));

        $response->assertOk();
        $response->assertSee('Nepali Chiya Patti');
    }

    public function test_user_can_filter_products_by_search_and_category(): void
    {
        [$shop, $user] = $this->createShopWithStaff('Inventory Staff');

        $cat1 = Category::factory()->create(['shop_id' => $shop->id, 'name' => 'Beverages']);
        $cat2 = Category::factory()->create(['shop_id' => $shop->id, 'name' => 'Snacks']);

        $p1 = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $cat1->id,
            'name' => 'Tokla Tea',
            'sku' => 'TOKLA-01',
        ]);

        $p2 = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $cat2->id,
            'name' => 'Current Noodles',
            'sku' => 'CURR-01',
        ]);

        // Search by name
        $res = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.index', ['search' => 'Tokla']));
        $res->assertOk();
        $res->assertSee('Tokla Tea');
        $res->assertDontSee('Current Noodles');

        // Filter by category
        $resCat = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('products.index', ['category_id' => $cat2->id]));
        $resCat->assertOk();
        $resCat->assertSee('Current Noodles');
        $resCat->assertDontSee('Tokla Tea');
    }

    public function test_user_with_products_manage_can_create_product(): void
    {
        [$shop, $user] = $this->createShopWithStaff('Shop Owner');

        $unit = Unit::firstOrCreate(['symbol' => 'pkt'], [
            'name' => 'Packet',
            'is_fractional' => false,
        ]);
        $category = Category::factory()->create(['shop_id' => $shop->id]);

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.store'), [
                'name' => 'Chowmein Packet',
                'sku' => 'CHOW-100',
                'barcode' => '8901234567890',
                'category_id' => $category->id,
                'unit_id' => $unit->id,
                'cost_price' => '35.00',
                'selling_price' => '45.00',
                'min_stock_alert' => '10.000',
                'is_active' => '1',
            ]);

        $response->assertRedirect(route('products.index'));

        $this->assertDatabaseHas('products', [
            'shop_id' => $shop->id,
            'name' => 'Chowmein Packet',
            'sku' => 'CHOW-100',
            'cost_price' => '35.00',
            'selling_price' => '45.00',
        ]);
    }

    public function test_product_creation_validates_required_fields(): void
    {
        [$shop, $user] = $this->createShopWithStaff('Shop Owner');

        $response = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.store'), [
                'name' => '',
                'unit_id' => '',
                'cost_price' => '-10',
                'selling_price' => '',
            ]);

        $response->assertSessionHasErrors(['name', 'unit_id', 'cost_price', 'selling_price']);
    }

    public function test_user_without_products_manage_cannot_create_or_update_product(): void
    {
        [$shop, $user] = $this->createShopWithStaff('Cashier');

        $product = Product::factory()->create(['shop_id' => $shop->id]);

        // Attempt create -> 403
        $resCreate = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->post(route('products.store'), [
                'name' => 'Unauthorized Product',
                'unit_id' => 1,
                'cost_price' => '10.00',
                'selling_price' => '15.00',
            ]);
        $resCreate->assertForbidden();

        // Attempt update -> 403
        $resUpdate = $this->actingAs($user)
            ->withSession(['current_shop_id' => $shop->id])
            ->put(route('products.update', $product), [
                'name' => 'Hacked Name',
                'unit_id' => 1,
                'cost_price' => '10.00',
                'selling_price' => '15.00',
            ]);
        $resUpdate->assertForbidden();
    }

    public function test_cross_tenant_product_url_access_returns_404(): void
    {
        [$shopA, $userA] = $this->createShopWithStaff('Shop Owner');
        [$shopB] = $this->createShopWithStaff('Shop Owner');

        TenantContext::setShop($shopB);
        $productB = Product::factory()->create(['shop_id' => $shopB->id]);

        // User from Shop A tries to view or edit Product belonging to Shop B
        $response = $this->actingAs($userA)
            ->withSession(['current_shop_id' => $shopA->id])
            ->get(route('products.edit', $productB));

        $response->assertNotFound();
    }
}
