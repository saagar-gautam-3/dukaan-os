<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\ReminderLog;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * T7a: every user-supplied text field is stored through its real create route
 * and then rendered on every page that shows it. The raw payload must never
 * appear; the escaped form must (proving the value really is displayed).
 */
final class XssEscapingTest extends TestCase
{
    use RefreshDatabase;

    private const PAYLOAD = '<script>alert(1)</script>"><img src=x onerror=alert(1)>';

    private Shop $shop;

    private User $owner;

    private Warehouse $warehouse;

    private Unit $unit;

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

        $this->warehouse = Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);
        $this->unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);

        $this->actingAs($this->owner)->withSession(['current_shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_customer_name_and_address_are_escaped_on_every_customer_page(): void
    {
        $this->post(route('customers.store'), [
            'name' => self::PAYLOAD,
            'phone' => '9800000001',
            'address' => self::PAYLOAD,
        ])->assertSessionHasNoErrors();

        $customer = Customer::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $customer->name);
        $this->assertSame(self::PAYLOAD, $customer->address);

        $this->assertEscapedOnPages('customer', [
            route('customers.index'),
            route('customers.show', $customer),
            route('customers.edit', $customer),
            route('pos'),
            route('udhar.outstanding'),
            route('udhar.collect', $customer),
            route('sales.index'),
        ], route('customers.show', $customer));
    }

    public function test_product_name_ne_and_sku_are_escaped_on_every_product_page(): void
    {
        $category = Category::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Staples']);
        $this->shop->update(['settings' => ['sku_auto_generate' => false]]);

        $this->post(route('products.store'), [
            'name' => self::PAYLOAD,
            'name_ne' => self::PAYLOAD,
            'sku' => self::PAYLOAD,
            'unit_id' => $this->unit->id,
            'category_id' => $category->id,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
        ])->assertSessionHasNoErrors();

        $product = Product::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $product->name);
        $this->assertSame(self::PAYLOAD, $product->sku);

        $this->assertEscapedOnPages('product', [
            route('products.index'),
            route('products.edit', $product),
            route('stock.index'),
            route('stock.alerts'),
            route('dashboard'),
            route('pos'),
            route('purchases.create'),
        ], route('products.index'));
    }

    public function test_supplier_name_and_address_are_escaped_on_every_supplier_page(): void
    {
        $this->post(route('suppliers.store'), [
            'name' => self::PAYLOAD,
            'address' => self::PAYLOAD,
        ])->assertSessionHasNoErrors();

        $supplier = Supplier::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $supplier->name);

        $this->assertEscapedOnPages('supplier', [
            route('suppliers.index'),
            route('suppliers.edit', $supplier),
            route('purchases.index'),
            route('purchases.create'),
        ], route('suppliers.index'));
    }

    public function test_expense_category_and_description_are_escaped_on_every_expense_page(): void
    {
        $this->post(route('expense-categories.store'), ['name' => self::PAYLOAD])
            ->assertSessionHasNoErrors();

        $expenseCategory = ExpenseCategory::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $expenseCategory->name);

        $this->post(route('expenses.store'), [
            'category_id' => $expenseCategory->id,
            'amount' => '50.00',
            'expense_date' => now($this->shop->timezone)->toDateString(),
            'payment_method' => 'cash',
            'description' => self::PAYLOAD,
        ])->assertSessionHasNoErrors();

        $expense = Expense::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $expense->description);

        $this->assertEscapedOnPages('expense', [
            route('expenses.index'),
            route('expenses.edit', $expense),
            route('expense-categories.index'),
            route('reports.daily-summary', ['date' => now($this->shop->timezone)->toDateString()]),
            route('expenses.create'),
        ], route('expenses.index'));
    }

    public function test_category_name_is_escaped_on_every_page_that_shows_it(): void
    {
        $this->post(route('categories.store'), ['name' => self::PAYLOAD])
            ->assertSessionHasNoErrors();

        $category = Category::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $category->name);

        $this->assertEscapedOnPages('category', [
            route('categories.index'),
            route('products.index'),
            route('products.create'),
            route('dashboard'),
        ], route('categories.index'));
    }

    public function test_staff_name_is_escaped_on_every_staff_page(): void
    {
        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);

        $this->post(route('staff.store'), [
            'name' => self::PAYLOAD,
            'email' => 'xss.staff@example.test',
            'phone' => '9812345678',
            'role_id' => $roles['Cashier']->id,
            'password' => 'Str0ngPass!23',
        ])->assertSessionHasNoErrors();

        $member = ShopUser::where('shop_id', $this->shop->id)
            ->whereHas('user', fn ($query) => $query->where('name', self::PAYLOAD))
            ->firstOrFail();

        $this->assertEscapedOnPages('staff', [
            route('staff.index'),
            route('staff.edit', $member),
        ], route('staff.index'));
    }

    public function test_shop_name_and_address_are_escaped_on_every_page_that_shows_them(): void
    {
        $this->put(route('settings.shop.update'), [
            'name' => self::PAYLOAD,
            'business_type' => 'kirana',
            'timezone' => 'Asia/Kathmandu',
            'address' => self::PAYLOAD,
        ])->assertSessionHasNoErrors();

        $this->shop->refresh();
        $this->assertSame(self::PAYLOAD, $this->shop->name);

        $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Ram']);
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'name' => 'Rice',
            'sku' => 'RICE-1',
            'selling_price' => '100.00',
        ]);
        $this->post(route('sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'post_immediately' => false,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '100.00',
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();

        $this->assertEscapedOnPages('shop', [
            route('settings.shop'),
            route('dashboard'),
            route('sales.invoice', $sale),
            route('sales.thermal', $sale),
        ], route('settings.shop'));
    }

    public function test_sale_notes_are_escaped_on_show_invoice_and_thermal(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Ram']);
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'name' => 'Rice',
            'sku' => 'RICE-2',
            'selling_price' => '100.00',
        ]);

        $this->post(route('sales.store'), [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'notes' => self::PAYLOAD,
            'post_immediately' => false,
            'items' => [[
                'product_id' => $product->id,
                'quantity' => '1.000',
                'unit_price' => '100.00',
            ]],
        ])->assertSessionHasNoErrors();

        $sale = Sale::where('shop_id', $this->shop->id)->latest('id')->firstOrFail();
        $this->assertSame(self::PAYLOAD, $sale->notes);

        $this->assertEscapedOnPages('sale notes', [
            route('sales.show', $sale),
            route('sales.invoice', $sale),
        ], route('sales.invoice', $sale));
    }

    public function test_reminder_message_preview_is_escaped_on_customer_page(): void
    {
        $customer = Customer::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Ram',
            'phone' => '9800000002',
        ]);

        $response = $this->post(route('udhar.remind', $customer), ['message' => self::PAYLOAD]);
        $this->assertSame(302, $response->status(), 'Reminder route did not redirect: '.$response->status());
        $response->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->assertSame(
            self::PAYLOAD,
            ReminderLog::where('shop_id', $this->shop->id)->latest('id')->value('message_preview')
        );

        $this->assertEscapedOnPages('reminder', [
            route('customers.show', $customer),
        ], route('customers.show', $customer));
    }

    public function test_unit_names_have_no_user_writable_route(): void
    {
        $unitRoutes = collect(Route::getRoutes())
            ->map(fn ($route): ?string => $route->getName())
            ->filter(fn (?string $name): bool => $name !== null && str_starts_with($name, 'units.'))
            ->values()
            ->all();

        $this->assertSame(['units.index'], $unitRoutes, 'Units are seeded read-only; no create/update route may exist.');
    }

    /**
     * @param  list<string>  $urls
     */
    private function assertEscapedOnPages(string $label, array $urls, string $pageShowingValue): void
    {
        $escaped = e(self::PAYLOAD);
        $escapedSeen = false;

        foreach ($urls as $url) {
            $response = $this->get($url);
            $this->assertLessThan(500, $response->status(), $label.' page returned a server error: '.$url);
            $this->assertNotSame(404, $response->status(), $label.' page not found: '.$url);

            $html = (string) $response->getContent();
            $this->assertStringNotContainsString(self::PAYLOAD, $html, 'Unescaped '.$label.' payload on '.$url);

            if ($url === $pageShowingValue) {
                $this->assertStringContainsString($escaped, $html, 'Escaped '.$label.' value missing on '.$url);
                $escapedSeen = true;
            }
        }

        $this->assertTrue($escapedSeen, 'No page proved the escaped '.$label.' value renders.');
    }
}
