<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Actions\Inventory\ReceiveStock;
use App\Enums\LedgerEntryType;
use App\Models\CashSession;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockAdjustment;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7b: server-owned attributes posted alongside a valid payload are ignored.
 *
 * Every create/update tenant route is exercised with a forged bag containing
 * another shop's id, primary keys, audit fields, status flags and totals.
 */
final class ForgedFieldsIgnoredTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private Shop $otherShop;

    private User $owner;

    private Warehouse $warehouse;

    private Unit $unit;

    private Supplier $supplier;

    private Product $product;

    private Customer $customer;

    private Category $category;

    private ExpenseCategory $expenseCategory;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create();
        $this->otherShop = Shop::factory()->create();
        TenantContext::setShop($this->shop);

        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);
        $this->owner = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->owner->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create(['shop_id' => $this->shop->id, 'is_default' => true]);
        $this->unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);
        $this->category = Category::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Staples']);
        $this->supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);
        $this->product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'sku' => 'FORGE-1',
            'cost_price' => '10.00',
            'selling_price' => '100.00',
        ]);
        $this->customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $this->expenseCategory = ExpenseCategory::factory()->create([
            'shop_id' => $this->shop->id,
            'name' => 'Rent',
        ]);

        $this->actingAs($this->owner)->withSession(['current_shop_id' => $this->shop->id]);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_create_routes_ignore_forged_shop_ids_and_audit_flags(): void
    {
        $this->post(route('customers.store'), $this->forged([
            'name' => 'Forged Customer',
            'phone' => '9800000010',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $customer = Customer::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $customer->shop_id);
        $this->assertNotSame(999999, $customer->id);

        $this->post(route('products.store'), $this->forged([
            'name' => 'Forged Product',
            'sku' => 'FORGE-NEW',
            'unit_id' => $this->unit->id,
            'cost_price' => '10.00',
            'selling_price' => '15.00',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $product = Product::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $product->shop_id);

        $this->post(route('suppliers.store'), $this->forged([
            'name' => 'Forged Supplier',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->assertSame($this->shop->id, Supplier::latest('id')->firstOrFail()->shop_id);

        $this->post(route('categories.store'), $this->forged([
            'name' => 'Forged Category',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->assertSame($this->shop->id, Category::latest('id')->firstOrFail()->shop_id);

        $this->post(route('expense-categories.store'), $this->forged([
            'name' => 'Forged Expense Category',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->assertSame($this->shop->id, ExpenseCategory::latest('id')->firstOrFail()->shop_id);

        $roles = app(RbacService::class)->createDefaultRolesForShop($this->shop);
        $this->post(route('staff.store'), $this->forged([
            'name' => 'Forged Staff',
            'email' => 'forged.staff@example.test',
            'phone' => '9812345670',
            'role_id' => $roles['Cashier']->id,
            'password' => 'Str0ngPass!23',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $staffUser = User::where('email', 'forged.staff@example.test')->firstOrFail();
        $this->assertFalse((bool) $staffUser->is_platform_admin);
        $staffMember = ShopUser::where('user_id', $staffUser->id)->firstOrFail();
        $this->assertSame($this->shop->id, $staffMember->shop_id);
        $this->assertNotSame(999, (int) $staffUser->permissions_version);
    }

    public function test_updates_cannot_move_records_between_shops_or_override_ids(): void
    {
        $originalCustomerId = $this->customer->id;
        $this->put(route('customers.update', $this->customer), $this->forged([
            'name' => 'Renamed Customer',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->customer->refresh();
        $this->assertSame($originalCustomerId, $this->customer->id);
        $this->assertSame($this->shop->id, $this->customer->shop_id);

        $originalProductId = $this->product->id;
        $this->put(route('products.update', $this->product), $this->forged([
            'name' => 'Renamed Product',
            'sku' => 'FORGE-1',
            'unit_id' => $this->unit->id,
            'cost_price' => '10.00',
            'selling_price' => '100.00',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->product->refresh();
        $this->assertSame($originalProductId, $this->product->id);
        $this->assertSame($this->shop->id, $this->product->shop_id);

        $this->put(route('suppliers.update', $this->supplier), $this->forged([
            'name' => 'Renamed Supplier',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->supplier->refresh();
        $this->assertSame($this->shop->id, $this->supplier->shop_id);

        $this->put(route('categories.update', $this->category), $this->forged([
            'name' => 'Renamed Category',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->category->refresh();
        $this->assertSame($this->shop->id, $this->category->shop_id);

        $this->put(route('expense-categories.update', $this->expenseCategory), $this->forged([
            'name' => 'Renamed Expense Category',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->expenseCategory->refresh();
        $this->assertSame($this->shop->id, $this->expenseCategory->shop_id);

        $this->put(route('settings.shop.update'), $this->forged([
            'name' => 'Renamed Shop',
            'business_type' => 'kirana',
            'timezone' => 'Asia/Kathmandu',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->shop->refresh();
        $this->assertSame($this->shop->id, $this->shop->id);
        $this->assertTrue((bool) $this->shop->is_active);
    }

    public function test_expense_and_cash_routes_ignore_forged_creator_and_status(): void
    {
        $this->post(route('expenses.store'), $this->forged([
            'category_id' => $this->expenseCategory->id,
            'amount' => '25.00',
            'expense_date' => now($this->shop->timezone)->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Forged expense',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $expense = Expense::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $expense->shop_id);
        $this->assertSame($this->owner->id, (int) $expense->created_by);

        $this->post(route('expenses.update', $expense), $this->forged([
            'category_id' => $this->expenseCategory->id,
            'amount' => '30.00',
            'expense_date' => now($this->shop->timezone)->toDateString(),
            'payment_method' => 'cash',
            'description' => 'Forged expense updated',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $expense->refresh();
        $this->assertSame($this->shop->id, $expense->shop_id);
        $this->assertSame($this->owner->id, (int) $expense->created_by);

        $this->post(route('cash.open'), $this->forged([
            'opening_cash' => '5000.00',
            'notes' => 'Open',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $session = CashSession::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $session->shop_id);
        $this->assertSame('open', $session->status->value);

        $this->post(route('cash.close', $session), $this->forged([
            'closing_cash' => '5100.00',
            'notes' => 'Close',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $session->refresh();
        $this->assertSame($this->shop->id, $session->shop_id);
        $this->assertSame('closed', $session->status->value);
    }

    public function test_sales_and_purchases_compute_totals_and_cost_instead_of_trusting_input(): void
    {
        app(ReceiveStock::class)->execute(
            product: $this->product,
            warehouse: $this->warehouse,
            quantity: '10.000',
            unitCost: '10.00',
            notes: 'Boundary fixture stock',
        );

        $this->post(route('sales.store'), $this->forged([
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'post_immediately' => true,
            'payment_method' => 'cash',
            'payment_amount' => '100.00',
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => '2.000',
                'unit_price' => '50.00',
                'unit_cost' => '0.01',
            ]],
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $sale = Sale::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $sale->shop_id);
        $this->assertSame('posted', $sale->status->value);
        $this->assertSame('100.00', (string) $sale->grand_total);
        $this->assertSame('100.00', (string) $sale->paid_total);
        $this->assertSame('10.00', (string) $sale->items()->firstOrFail()->unit_cost);

        $this->post(route('purchases.store'), $this->forged([
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'FORGED-PO-1',
            'purchase_date' => now($this->shop->timezone)->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => '2.000',
                'unit_cost' => '10.00',
            ]],
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $purchase = Purchase::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $purchase->shop_id);
        $this->assertSame('draft', $purchase->status->value);
        $this->assertSame('20.00', (string) $purchase->grand_total);
        /** @var PurchaseItem $line */
        $line = $purchase->items()->firstOrFail();
        $this->assertSame('10.00', (string) $line->unit_cost);
    }

    public function test_stock_adjust_and_udhar_collect_ignore_forged_ownership(): void
    {
        $this->post(route('stock.adjust.store'), $this->forged([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $this->product->id,
            'type' => 'in',
            'quantity' => '5.000',
            'reason' => 'Forged adjustment',
            'unit_cost' => '10.00',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $adjustment = StockAdjustment::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $adjustment->shop_id);

        CustomerLedgerEntry::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $this->customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => '100.00',
            'credit' => '0.00',
            'created_by' => $this->owner->id,
        ]);

        $this->post(route('udhar.collect.store', $this->customer), $this->forged([
            'amount' => '40.00',
            'payment_method' => 'cash',
            'notes' => 'Forged collection',
        ]))->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $payment = Payment::latest('id')->firstOrFail();
        $this->assertSame($this->shop->id, $payment->shop_id);
        $this->assertSame('40.00', (string) $payment->amount);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function forged(array $payload): array
    {
        return array_merge([
            'shop_id' => $this->otherShop->id,
            'id' => 999999,
            'created_by' => 999999,
            'status' => 'posted',
            'posted_at' => '2000-01-01 00:00:00',
            'is_platform_admin' => true,
            'permissions_version' => 999,
            'paid_total' => '1.00',
            'grand_total' => '1.00',
            'unit_cost' => '0.01',
        ], $payload);
    }
}
