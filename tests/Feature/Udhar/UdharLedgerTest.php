<?php

declare(strict_types=1);

namespace Tests\Feature\Udhar;

use App\Actions\Inventory\PostPurchase;
use App\Actions\Inventory\ReceiveStock;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\RecordPayment;
use App\Actions\Sales\ReturnSale;
use App\Actions\Udhar\CollectPayment;
use App\Actions\Udhar\SendReminder;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\ReminderLog;
use App\Models\Sale;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class UdharLedgerTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    private Warehouse $warehouse;

    private Unit $unit;

    private Category $category;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => false],
        ]);
        TenantContext::setShop($this->shop);

        $rbac = app(RbacService::class);
        $rbac->createDefaultRolesForShop($this->shop);

        $this->user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'role_id' => $this->shop->roles()->where('name', 'Shop Owner')->first()->id,
            'status' => 'active',
        ]);

        $this->warehouse = Warehouse::factory()->create([
            'shop_id' => $this->shop->id,
            'is_default' => true,
        ]);

        $this->unit = Unit::factory()->create();
        $this->category = Category::factory()->create(['shop_id' => $this->shop->id]);
    }

    private function createStockedProduct(string $costPrice = '100.00', string $sellingPrice = '150.00', float $qty = 50.0): Product
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'track_stock' => true,
        ]);

        app(ReceiveStock::class)->execute(
            product: $product,
            warehouse: $this->warehouse,
            quantity: $qty,
            unitCost: $costPrice,
            userId: $this->user->id,
        );

        return $product;
    }

    public function test_customer_balance_equals_sum_debit_minus_sum_credit(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'debit' => '1000.00',
            'credit' => '0.00',
        ]);

        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'debit' => '500.00',
            'credit' => '0.00',
        ]);

        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'debit' => '0.00',
            'credit' => '400.00',
        ]);

        // 1000 + 500 - 400 = 1100.00
        $this->assertSame('1100.00', $customer->ledgerBalance());
    }

    public function test_ledger_entries_are_append_only_and_cannot_be_updated(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $entry = CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'debit' => '250.00',
            'credit' => '0.00',
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['debit' => '300.00']);
    }

    public function test_ledger_entries_cannot_be_deleted(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $entry = CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'debit' => '250.00',
            'credit' => '0.00',
        ]);

        $this->expectException(LogicException::class);
        $entry->delete();
    }

    public function test_supplier_ledger_entries_cannot_be_updated_or_deleted(): void
    {
        $supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);

        $entry = SupplierLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'supplier_id' => $supplier->id,
            'debit' => '1500.00',
            'credit' => '0.00',
        ]);

        $this->expectException(LogicException::class);
        $entry->update(['debit' => '2000.00']);
    }

    public function test_post_sale_on_credit_writes_ledger_debit_for_grand_total(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '200.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 2,
                    'unit_price' => '200.00',
                ],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '0.00',
        ]);

        // Sale is 400.00 with 0 paid => ledger should have 1 debit entry for 400.00
        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::SaleDebit->value,
            'debit' => '400.00',
            'credit' => '0.00',
        ]);

        $this->assertSame('400.00', $customer->ledgerBalance());
    }

    public function test_post_sale_with_partial_payment_writes_debit_and_credit_entries(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '500.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '200.00',
            'items' => [
                [
                    'product_id' => $product->id,
                    'quantity' => 1,
                    'unit_price' => '500.00',
                ],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '200.00',
        ]);

        // Should have debit for 500 and credit for 200
        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::SaleDebit->value,
            'debit' => '500.00',
        ]);

        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PaymentCredit->value,
            'credit' => '200.00',
        ]);

        // Balance = 500 - 200 = 300.00
        $this->assertSame('300.00', $customer->ledgerBalance());
    }

    public function test_record_payment_and_allocate_payment_write_ledger_credit(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '1000.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '1000.00'],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '0.00']);
        $this->assertSame('1000.00', $customer->ledgerBalance());

        // Now record payment
        app(RecordPayment::class)->handle(
            $this->user->id,
            [
                'customer_id' => $customer->id,
                'amount' => '300.00',
                'method' => 'cash',
                'allocate_to_sale_id' => $sale->id,
                'notes' => 'Partial payment',
            ],
        );

        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PaymentCredit->value,
            'credit' => '300.00',
        ]);

        $this->assertSame('700.00', $customer->ledgerBalance());
    }

    public function test_return_sale_writes_ledger_credit(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '500.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '500.00'],
            ],
        ], $this->user->id);

        $sale = app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '0.00']);
        $this->assertSame('1000.00', $customer->ledgerBalance());

        $saleItem = $sale->items->first();

        // Return 1 quantity (worth 500.00)
        app(ReturnSale::class)->handle(
            $sale,
            $this->user->id,
            [
                ['sale_item_id' => $saleItem->id, 'quantity' => '1'],
            ],
            'Defective',
        );

        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ReturnCredit->value,
            'credit' => '500.00',
        ]);

        $this->assertSame('500.00', $customer->ledgerBalance());
    }

    public function test_post_purchase_writes_supplier_ledger_entry(): void
    {
        $supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'cost_price' => '200.00',
            'selling_price' => '300.00',
        ]);

        $purchase = Purchase::create([
            'shop_id' => $this->shop->id,
            'purchase_no' => 'PO-000001',
            'purchase_date' => now()->toDateString(),
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => 'draft',
            'subtotal' => '2000.00',
            'tax_amount' => '0.00',
            'grand_total' => '2000.00',
            'created_by' => $this->user->id,
        ]);

        PurchaseItem::create([
            'shop_id' => $this->shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
            'quantity' => 10,
            'unit_cost' => '200.00',
            'line_total' => '2000.00',
        ]);

        app(PostPurchase::class)->execute($purchase, $this->user->id);

        $this->assertDatabaseHas('supplier_ledger_entries', [
            'shop_id' => $this->shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => LedgerEntryType::PurchaseDebit->value,
            'debit' => '2000.00',
            'credit' => '0.00',
        ]);

        $this->assertSame('2000.00', $supplier->ledgerBalance());
    }

    public function test_collection_flow_allocates_oldest_due_first_and_leftover_becomes_advance(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '100.00', qty: 100.0);

        // Sale 1: Due 300.00 (older due date)
        $sale1 = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'due_date' => now()->subDays(10)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 3, 'unit_price' => '100.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale1, $this->user->id, ['payment_amount' => '0.00']);

        // Sale 2: Due 400.00 (newer due date)
        $sale2 = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'due_date' => now()->addDays(5)->toDateString(),
            'items' => [['product_id' => $product->id, 'quantity' => 4, 'unit_price' => '100.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale2, $this->user->id, ['payment_amount' => '0.00']);

        // Total due across sales = 700.00
        $this->assertSame('700.00', $customer->ledgerBalance());

        // Collect 800.00:
        // - Sale 1 should be fully paid (300.00)
        // - Sale 2 should be fully paid (400.00)
        // - Leftover 100.00 should become Advance Credit
        $result = app(CollectPayment::class)->handle(
            customer: $customer,
            userId: $this->user->id,
            data: [
                'amount' => '800.00',
                'method' => 'cash',
                'notes' => 'Overpayment collection',
            ],
        );

        $this->assertSame('300.00', $result['allocated'][$sale1->id]);
        $this->assertSame('400.00', $result['allocated'][$sale2->id]);
        $this->assertSame('100.00', $result['advance']);

        $sale1->refresh();
        $sale2->refresh();
        $this->assertSame('300.00', (string) $sale1->paid_total);
        $this->assertSame('400.00', (string) $sale2->paid_total);

        // Advance entry recorded in ledger
        $this->assertDatabaseHas('customer_ledger_entries', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::AdvanceCredit->value,
            'credit' => '100.00',
        ]);

        // Net balance: 700 debit - 800 credit = -100.00 (advance)
        $this->assertSame('-100.00', $customer->ledgerBalance());
    }

    public function test_collection_flow_with_custom_allocations(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '200.00', qty: 50.0);

        $sale1 = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '200.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale1, $this->user->id, ['payment_amount' => '0.00']);

        $sale2 = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '200.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale2, $this->user->id, ['payment_amount' => '0.00']);

        // Explicitly allocate 150 to sale2 and 50 to sale1 out of 250 collected
        $result = app(CollectPayment::class)->handle(
            customer: $customer,
            userId: $this->user->id,
            data: [
                'amount' => '250.00',
                'method' => 'qr',
                'notes' => 'Custom allocation test',
                'allocations' => [
                    $sale2->id => '150.00',
                    $sale1->id => '50.00',
                ],
            ],
        );

        $this->assertSame('50.00', $result['advance']);
        $this->assertSame('50.00', $result['allocated'][$sale1->id]);
        $this->assertSame('150.00', $result['allocated'][$sale2->id]);

        $sale1->refresh();
        $sale2->refresh();
        $this->assertSame('50.00', (string) $sale1->paid_total);
        $this->assertSame('150.00', (string) $sale2->paid_total);
    }

    public function test_reconciliation_command_reports_no_drift_when_consistent(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createStockedProduct(sellingPrice: '350.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '100.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '350.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '100.00']);

        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('All customer balances match ledger sums');
    }

    public function test_reconciliation_command_reports_drift_when_inconsistent(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        // Manually inject a drift by creating an extra manual ledger debit without a corresponding sale
        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::ManualDebit->value,
            'debit' => '999.00',
            'credit' => '0.00',
        ]);

        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('DRIFT DETECTED');
    }

    public function test_send_reminder_masks_phone_number_in_reminder_logs(): void
    {
        $customer = Customer::factory()->create([
            'shop_id' => $this->shop->id,
            'phone' => '9841234567',
        ]);

        app(SendReminder::class)->handle($customer, ['message' => 'Please pay your dues.']);

        $this->assertDatabaseHas('reminder_logs', [
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'phone_masked' => '98****4567',
            'channel' => 'stub',
            'status' => 'sent',
        ]);

        // Verify the full phone is NEVER stored in reminder_logs
        $log = ReminderLog::where('customer_id', $customer->id)->first();
        $this->assertNotNull($log);
        $this->assertStringNotContainsString('9841234567', $log->phone_masked);
    }

    public function test_unauthorized_user_cannot_collect_udhar(): void
    {
        $unauthorizedUser = User::factory()->create();
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $this->actingAs($unauthorizedUser);

        $response = $this->get(route('udhar.collect', $customer));
        $response->assertForbidden();
    }

    public function test_cashier_can_collect_payment(): void
    {
        $cashierUser = User::factory()->create();
        $cashierRole = $this->shop->roles()->where('name', 'Cashier')->first();

        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $cashierUser->id,
            'role_id' => $cashierRole->id,
            'status' => 'active',
        ]);

        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $this->actingAs($cashierUser);

        $response = $this->get(route('udhar.collect', $customer));
        $response->assertOk();
    }

    public function test_customer_ledger_entry_cross_tenant_isolation(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);
        $otherCustomer = Customer::factory()->create(['shop_id' => $otherShop->id]);

        CustomerLedgerEntry::factory()->create([
            'shop_id' => $otherShop->id,
            'customer_id' => $otherCustomer->id,
            'debit' => '500.00',
        ]);

        // Current shop context is $this->shop
        TenantContext::setShop($this->shop);
        $entries = CustomerLedgerEntry::all();
        $this->assertCount(0, $entries);
    }

    public function test_supplier_ledger_entry_cross_tenant_isolation(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);
        $otherSupplier = Supplier::factory()->create(['shop_id' => $otherShop->id]);

        SupplierLedgerEntry::factory()->create([
            'shop_id' => $otherShop->id,
            'supplier_id' => $otherSupplier->id,
            'debit' => '750.00',
        ]);

        TenantContext::setShop($this->shop);
        $entries = SupplierLedgerEntry::all();
        $this->assertCount(0, $entries);
    }

    public function test_reminder_log_cross_tenant_isolation(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);
        $otherCustomer = Customer::factory()->create(['shop_id' => $otherShop->id]);

        ReminderLog::factory()->create([
            'shop_id' => $otherShop->id,
            'customer_id' => $otherCustomer->id,
        ]);

        TenantContext::setShop($this->shop);
        $logs = ReminderLog::all();
        $this->assertCount(0, $logs);
    }
}
