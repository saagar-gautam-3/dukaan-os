<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerAndPosTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $cashier;

    private User $owner;

    private Warehouse $warehouse;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($this->shop);

        // Owner
        $this->owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        // Cashier
        $this->cashier = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->cashier->id,
            'role_id' => $roles['Cashier']->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);

        $this->unit = Unit::firstOrCreate(['symbol' => 'pkt'], [
            'name' => 'Packet',
            'is_fractional' => false,
        ]);

        $this->category = Category::factory()->create(['shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_pos_screen_renders_for_cashier(): void
    {
        $response = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get('/pos');

        $response->assertStatus(200);
        $response->assertSee(__('sales.pos_title'));
        $response->assertSee('productSearchInput');
    }

    public function test_pos_product_search_json_api(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Wai Wai Noodles',
            'name_ne' => 'वाइवाइ चाउचाउ',
            'sku' => 'WAIWAI-75G',
            'selling_price' => '25.00',
            'track_stock' => true,
            'is_active' => true,
        ]);

        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $this->warehouse,
            quantity: 40.0,
            unitCost: 18.0,
            notes: 'Initial stock'
        );

        $response = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->getJson('/pos/products?q=Wai');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $product->id,
            'name' => 'Wai Wai Noodles',
            'name_ne' => 'वाइवाइ चाउचाउ',
            'sku' => 'WAIWAI-75G',
            'price' => '25.00',
        ]);

        // Search by Nepali name
        $nepaliResponse = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->getJson('/pos/products?q='.urlencode('वाइवाइ'));

        $nepaliResponse->assertStatus(200);
        $nepaliResponse->assertJsonFragment([
            'id' => $product->id,
            'name' => 'Wai Wai Noodles',
            'name_ne' => 'वाइवाइ चाउचाउ',
        ]);
    }

    public function test_pos_customer_search_json_api(): void
    {
        $customer = Customer::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Hari Krishna',
            'phone' => '9841234567',
        ]);

        $response = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->getJson('/pos/customers?q=Hari');

        $response->assertStatus(200);
        $response->assertJsonFragment([
            'id' => $customer->id,
            'name' => 'Hari Krishna',
            'phone' => '9841234567',
        ]);
    }

    public function test_pos_can_post_sale_immediately(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Biscuits',
            'sku' => 'BISC-50',
            'selling_price' => '50.00',
            'track_stock' => true,
        ]);

        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $this->warehouse,
            quantity: 20.0,
            unitCost: 35.0,
            notes: 'Stock'
        );

        $response = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post('/sales', [
                'warehouse_id' => $this->warehouse->id,
                'post_immediately' => '1',
                'payment_method' => 'cash',
                'payment_amount' => '100.00',
                'items' => [
                    [
                        'product_id' => $product->id,
                        'quantity' => '2.000',
                        'unit_price' => '50.00',
                    ],
                ],
            ]);

        $response->assertRedirect();

        $sale = Sale::latest()->first();
        $this->assertNotNull($sale);
        $this->assertTrue($sale->isPosted());
        $this->assertEquals('100.00', $sale->grand_total);
        $this->assertEquals('100.00', $sale->paid_total);
    }

    public function test_invoice_and_thermal_views_render(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $this->category->id,
            'unit_id' => $this->unit->id,
            'name' => 'Juice',
            'selling_price' => '120.00',
            'track_stock' => false,
        ]);

        $sale = app(CreateSale::class)->handle([
            'warehouse_id' => $this->warehouse->id,
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => '1.000',
                    'unit_price' => '120.00',
                ],
            ],
        ]);

        $posted = app(PostSale::class)->handle($sale, $this->cashier->id, [
            'payment_method' => 'cash',
            'payment_amount' => '120.00',
        ]);

        // A4 Invoice view
        $resA4 = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get("/sales/{$posted->id}/invoice");

        $resA4->assertStatus(200);
        $resA4->assertSee($posted->invoice_no);
        $resA4->assertSee('Juice');

        // Thermal 80mm view
        $resThermal = $this->actingAs($this->cashier)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get("/sales/{$posted->id}/thermal");

        $resThermal->assertStatus(200);
        $resThermal->assertSee($posted->invoice_no);
        $resThermal->assertSee('80mm');
    }

    public function test_customer_crud_flow(): void
    {
        // 1. Create customer
        $resStore = $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->post('/customers', [
                'name' => 'Sita Devi Sharma',
                'phone' => '9801122334',
                'email' => 'sita@example.com',
                'address' => 'Patan, Lalitpur',
                'credit_limit' => '5000.00',
            ]);

        $resStore->assertRedirect('/customers');

        $customer = Customer::where('phone', '9801122334')->first();
        $this->assertNotNull($customer);
        $this->assertEquals('Sita Devi Sharma', $customer->name);

        // 2. View customer details
        $resShow = $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->get("/customers/{$customer->id}");

        $resShow->assertStatus(200);
        $resShow->assertSee('Sita Devi Sharma');
        $resShow->assertSee('Patan, Lalitpur');

        // 3. Update customer
        $resUpdate = $this->actingAs($this->owner)
            ->withSession(['current_shop_id' => $this->shop->id])
            ->put("/customers/{$customer->id}", [
                'name' => 'Sita Devi Sharma (Updated)',
                'phone' => '9801122334',
                'credit_limit' => '8000.00',
            ]);

        $resUpdate->assertRedirect("/customers/{$customer->id}");
        $this->assertEquals('Sita Devi Sharma (Updated)', $customer->fresh()->name);
    }
}
