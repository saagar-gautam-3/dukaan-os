<?php

declare(strict_types=1);

namespace Tests\Feature\Pos;

use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\HeldSale;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockMovement;
use App\Models\Unit;
use App\Models\User;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HeldSaleTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $owner;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);
        $this->owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);
        $category = Category::factory()->create(['shop_id' => $this->shop->id]);
        $this->product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $unit->id,
            'category_id' => $category->id,
            'sku' => 'HOLD-1',
            'selling_price' => '10.00',
            'is_active' => true,
        ]);

        $this->actingAs($this->owner)->withSession(['current_shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_hold_stores_only_ids_and_quantities(): void
    {
        $response = $this->postJson(route('pos.held.store'), [
            'label' => 'Test hold',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => '2.000',
                'unit_price' => '999.00',
                'total' => '1000.00',
                'shop_id' => 999,
                'user_id' => 999,
            ]],
        ]);

        $response->assertStatus(201);

        $held = HeldSale::firstOrFail();
        $this->assertCount(1, $held->cart);
        $this->assertSame($this->product->id, $held->cart[0]['product_id']);
        $this->assertSame('2.000', $held->cart[0]['quantity']);
        $this->assertSame($this->owner->id, $held->user_id);
        $this->assertSame($this->shop->id, $held->shop_id);
    }

    public function test_hold_limit_21st_rejected(): void
    {
        HeldSale::factory()->count(20)->create(['shop_id' => $this->shop->id, 'user_id' => $this->owner->id]);

        $response = $this->postJson(route('pos.held.store'), [
            'items' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $response->assertStatus(422);
    }

    public function test_hold_line_limit_201_rejected(): void
    {
        $items = [];
        for ($i = 0; $i < 201; $i++) {
            $items[] = ['product_id' => $this->product->id, 'quantity' => '1.000'];
        }

        $response = $this->postJson(route('pos.held.store'), ['items' => $items]);

        $response->assertStatus(422);
    }

    public function test_hold_bad_quantity_rejected(): void
    {
        foreach (['0', '-1', 'abc'] as $qty) {
            $response = $this->postJson(route('pos.held.store'), [
                'items' => [['product_id' => $this->product->id, 'quantity' => $qty]],
            ]);
            $response->assertStatus(422);
        }
    }

    public function test_hold_other_shop_product_rejected(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::runAs($otherShop, function () use ($otherShop, &$otherProduct): void {
            $unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);
            $category = Category::factory()->create(['shop_id' => $otherShop->id]);
            $otherProduct = Product::factory()->create([
                'shop_id' => $otherShop->id,
                'unit_id' => $unit->id,
                'category_id' => $category->id,
                'selling_price' => '5.00',
                'is_active' => true,
            ]);
        });

        $response = $this->postJson(route('pos.held.store'), [
            'items' => [['product_id' => $otherProduct->id, 'quantity' => '1.000']],
        ]);

        $response->assertStatus(422);
    }

    public function test_resume_uses_current_price_and_removes_held_row(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '2.000']],
        ]);

        $this->product->update(['selling_price' => '20.00']);

        $response = $this->postJson(route('pos.held.resume', $held));

        $response->assertOk();
        $response->assertJsonPath('items.0.price', '20.00');
        $this->assertSame(0, HeldSale::count());
    }

    public function test_resume_drops_deleted_product(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $this->product->delete();

        $response = $this->postJson(route('pos.held.resume', $held));

        $response->assertOk();
        $this->assertSame([], $response->json('items'));
        $this->assertNotEmpty($response->json('dropped'));
        $this->assertSame(0, HeldSale::count());
    }

    public function test_resume_drops_inactive_product(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $this->product->update(['is_active' => false]);

        $response = $this->postJson(route('pos.held.resume', $held));

        $response->assertOk();
        $this->assertSame([], $response->json('items'));
        $this->assertNotEmpty($response->json('dropped'));
    }

    public function test_second_resume_fails(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $this->postJson(route('pos.held.resume', $held))->assertOk();

        // second attempt should 404
        $response = $this->postJson(route('pos.held.resume', $held));
        $response->assertStatus(404);
    }

    public function test_resume_does_not_create_sale_or_side_effects(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $salesBefore = Sale::count();
        $movementsBefore = StockMovement::count();
        $paymentsBefore = Payment::count();
        $ledgerBefore = CustomerLedgerEntry::count();

        $this->postJson(route('pos.held.resume', $held))->assertOk();

        $this->assertSame($salesBefore, Sale::count());
        $this->assertSame($movementsBefore, StockMovement::count());
        $this->assertSame($paymentsBefore, Payment::count());
        $this->assertSame($ledgerBefore, CustomerLedgerEntry::count());
    }

    public function test_delete_works(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $response = $this->deleteJson(route('pos.held.destroy', $held));
        $response->assertOk();
        $this->assertSame(0, HeldSale::count());
    }

    public function test_delete_via_get_impossible(): void
    {
        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $response = $this->get(route('pos.held.destroy', $held));
        $this->assertContains($response->getStatusCode(), [404, 405]);
    }

    public function test_tenancy_another_shop_user_cannot_access(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::runAs($otherShop, function () use ($otherShop, &$otherUser): void {
            $roles = app(RbacService::class)->createDefaultRolesForShop($otherShop);
            $otherUser = User::factory()->create();
            ShopUser::factory()->create([
                'shop_id' => $otherShop->id,
                'user_id' => $otherUser->id,
                'role_id' => $roles['Shop Owner']->id,
                'status' => 'active',
            ]);
        });

        TenantContext::runAs($this->shop, function () use (&$held): void {
            $held = HeldSale::create([
                'shop_id' => $this->shop->id,
                'user_id' => $this->owner->id,
                'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
            ]);
        });

        TenantContext::setShop($otherShop);
        $this->actingAs($otherUser)->withSession(['current_shop_id' => $otherShop->id]);

        $this->getJson(route('pos.held'))->assertOk()->assertJsonCount(0);
        $this->postJson(route('pos.held.resume', $held))->assertStatus(404);
        $this->deleteJson(route('pos.held.destroy', $held))->assertStatus(404);
    }

    public function test_permissions(): void
    {
        $noPermShop = Shop::factory()->create();
        TenantContext::runAs($noPermShop, function () use ($noPermShop, &$noPermUser): void {
            $roles = app(RbacService::class)->createDefaultRolesForShop($noPermShop);
            $noPermRole = Role::factory()->create(['name' => 'No Permissions', 'shop_id' => $noPermShop->id]);
            $noPermUser = User::factory()->create();
            ShopUser::factory()->create([
                'shop_id' => $noPermShop->id,
                'user_id' => $noPermUser->id,
                'role_id' => $noPermRole->id,
                'status' => 'active',
            ]);
        });

        TenantContext::runAs($noPermShop, function () use ($noPermShop, &$unit, &$category, &$product): void {
            $unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);
            $category = Category::factory()->create(['shop_id' => $noPermShop->id]);
            $product = Product::factory()->create([
                'shop_id' => $noPermShop->id,
                'unit_id' => $unit->id,
                'category_id' => $category->id,
                'selling_price' => '10.00',
                'is_active' => true,
            ]);
        });

        $this->actingAs($noPermUser)->withSession(['current_shop_id' => $noPermShop->id]);

        $this->postJson(route('pos.held.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => '1.000']],
        ])->assertStatus(403);

        // cashier with permission
        TenantContext::setShop($noPermShop);
        $roles = app(RbacService::class)->createDefaultRolesForShop($noPermShop);
        $cashier = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $noPermShop->id,
            'user_id' => $cashier->id,
            'role_id' => $roles['Cashier']->id,
            'status' => 'active',
        ]);

        $this->actingAs($cashier)->withSession(['current_shop_id' => $noPermShop->id]);
        $this->postJson(route('pos.held.store'), [
            'items' => [['product_id' => $product->id, 'quantity' => '1.000']],
        ])->assertStatus(201);
    }

    public function test_xss_label_and_customer_escaped(): void
    {
        $payload = '<img src=x onerror=alert(1)>';
        $customer = Customer::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => $payload,
        ]);

        $held = HeldSale::create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'customer_id' => $customer->id,
            'label' => $payload,
            'cart' => [['product_id' => $this->product->id, 'quantity' => '1.000']],
        ]);

        $response = $this->get(route('pos'));
        $response->assertOk();
        // list endpoint returns raw payload
        $list = $this->getJson(route('pos.held'))->assertOk();
        $this->assertSame($payload, $list->json('0.label'));
        $this->assertSame($payload, $list->json('0.customer_name'));
    }
}
