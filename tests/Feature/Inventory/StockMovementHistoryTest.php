<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Enums\MovementType;
use App\Models\Category;
use App\Models\Permission;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StockMovementHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        TenantContext::reset();

        parent::tearDown();
    }

    public function test_owner_can_view_product_stock_movement_history_with_creator_name(): void
    {
        [$shop, $owner, $product] = $this->createShopProductAndMovement();

        $this->actingAs($owner)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('stock.history', $product))
            ->assertOk()
            ->assertSee($owner->name);
    }

    public function test_user_of_another_shop_cannot_view_product_stock_movement_history(): void
    {
        [$shop, , $product] = $this->createShopProductAndMovement();
        $otherShop = Shop::factory()->create();
        $roles = app(RbacService::class)->createDefaultRolesForShop($otherShop);
        $otherUser = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $otherShop->id,
            'user_id' => $otherUser->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->actingAs($otherUser)
            ->withSession(['current_shop_id' => $otherShop->id])
            ->get(route('stock.history', $product->id))
            ->assertNotFound();
    }

    public function test_cashier_without_product_view_permission_cannot_view_stock_history(): void
    {
        [$shop, , $product] = $this->createShopProductAndMovement();
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $cashierRole = $roles['Cashier'];
        $cashierRole->permissions()->detach(Permission::where('name', 'products.view')->value('id'));
        $cashier = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $cashier->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        $this->actingAs($cashier)
            ->withSession(['current_shop_id' => $shop->id])
            ->get(route('stock.history', $product))
            ->assertForbidden();
    }

    /** @return array{Shop, User, Product} */
    private function createShopProductAndMovement(): array
    {
        $shop = Shop::factory()->create();
        $roles = app(RbacService::class)->createDefaultRolesForShop($shop);
        $owner = User::factory()->create(['name' => 'History Creator']);
        ShopUser::factory()->create([
            'shop_id' => $shop->id,
            'user_id' => $owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        TenantContext::setShop($shop);
        $category = Category::factory()->create(['shop_id' => $shop->id]);
        $unit = Unit::factory()->create();
        $product = Product::factory()->create([
            'shop_id' => $shop->id,
            'category_id' => $category->id,
            'unit_id' => $unit->id,
            'name' => 'History Product',
        ]);
        $warehouse = Warehouse::factory()->create(['shop_id' => $shop->id]);
        StockMovement::factory()->create([
            'shop_id' => $shop->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'created_by' => $owner->id,
            'movement_type' => MovementType::Opening,
        ]);

        return [$shop, $owner, $product];
    }
}
