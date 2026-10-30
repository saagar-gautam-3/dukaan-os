<?php

declare(strict_types=1);

namespace Tests\Feature\Udhar;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\RecordPayment;
use App\Actions\Sales\ReturnSale;
use App\Actions\Udhar\CollectPayment;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Part 0a: Prove the ledger is NOT double-counted.
 * Part 0b: Prove ledger:reconcile detects drift via raw DB tampering.
 *
 * Rules verified here:
 *  - Sale Rs 10,000, Rs 6,000 paid at POS → exactly 1 debit(10000) + 1 credit(6000); balance = 4000
 *  - CollectPayment Rs 4,000 → exactly 1 additional credit; balance = 0
 *  - RecordPayment with allocation → exactly 1 credit per payment, never two
 *  - Return → exactly 1 credit entry
 *  - ledger:reconcile exits 1 when ledger row tampered via raw DB
 *  - ledger:reconcile exits 1 when sale's paid_total cache tampered via raw DB
 */
final class LedgerDoubleCountTest extends TestCase
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
            'settings' => ['allow_negative_stock' => true],
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

    private function createProduct(string $costPrice = '500.00', string $sellingPrice = '1000.00'): Product
    {
        return Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'track_stock' => false, // Skip stock for double-count tests
        ]);
    }

    private function postSaleForCustomer(Customer $customer, string $sellingPrice, string $paidAtPos): array
    {
        $product = $this->createProduct(sellingPrice: $sellingPrice);

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => $paidAtPos,
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => $sellingPrice],
            ],
        ], $this->user->id);

        $sale = app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => $paidAtPos,
        ]);

        return [$sale, $customer];
    }

    /**
     * Part 0a — Test 1:
     * Sale Rs 10,000 with Rs 6,000 paid at POS creates EXACTLY 1 debit (10,000) and 1 credit (6,000).
     * Balance = 4,000 (not 0, not 10,000).
     */
    public function test_sale_10000_paid_6000_creates_exactly_one_debit_one_credit_balance_4000(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        [$sale] = $this->postSaleForCustomer($customer, '10000.00', '6000.00');

        $entries = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->get();

        // MUST have exactly 2 entries: 1 debit + 1 credit
        $this->assertCount(2, $entries, 'Expected exactly 2 ledger entries (1 debit + 1 credit)');

        $debits = $entries->where('entry_type', LedgerEntryType::SaleDebit->value)->values();
        $credits = $entries->where('entry_type', LedgerEntryType::PaymentCredit->value)->values();

        $this->assertCount(1, $debits, 'Expected exactly 1 debit entry');
        $this->assertCount(1, $credits, 'Expected exactly 1 credit entry');

        $this->assertSame('10000.00', (string) $debits[0]->debit, 'Debit must be 10000.00');
        $this->assertSame('0.00', (string) $debits[0]->credit);

        $this->assertSame('6000.00', (string) $credits[0]->credit, 'Credit must be 6000.00');
        $this->assertSame('0.00', (string) $credits[0]->debit);

        // Balance = 10000 - 6000 = 4000
        $this->assertSame('4000.00', $customer->fresh()->ledgerBalance(), 'Balance must be 4000.00');
    }

    /**
     * Part 0a — Test 2:
     * CollectPayment of Rs 4,000 on the outstanding balance adds EXACTLY one credit.
     * Balance becomes 0.
     */
    public function test_collect_payment_4000_adds_exactly_one_credit_balance_becomes_zero(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        [$sale] = $this->postSaleForCustomer($customer, '10000.00', '6000.00');
        $this->assertSame('4000.00', $customer->fresh()->ledgerBalance());

        // Count entries before collection
        $countBefore = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        app(CollectPayment::class)->handle(
            customer: $customer,
            userId: $this->user->id,
            data: [
                'amount' => '4000.00',
                'method' => 'cash',
                'notes' => 'Paying off the balance',
            ],
        );

        $countAfter = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        // Exactly 1 new credit entry added (the allocation credit)
        $this->assertSame(
            $countBefore + 1,
            $countAfter,
            'CollectPayment must add exactly 1 ledger entry'
        );

        // Balance must now be 0
        $this->assertSame('0.00', $customer->fresh()->ledgerBalance(), 'Balance must be 0 after full payment');
    }

    /**
     * Part 0a — Test 3:
     * RecordPayment with allocation adds EXACTLY 1 credit, never two.
     * This is the critical double-entry guard: RecordPayment writes the credit,
     * AllocatePayment only updates paid_total (no ledger write).
     */
    public function test_record_payment_with_allocation_adds_exactly_one_credit_never_two(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        [$sale] = $this->postSaleForCustomer($customer, '1000.00', '0.00');
        $this->assertSame('1000.00', $customer->fresh()->ledgerBalance());

        $countBefore = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        // RecordPayment with allocate_to_sale_id set
        app(RecordPayment::class)->handle(
            $this->user->id,
            [
                'customer_id' => $customer->id,
                'amount' => '300.00',
                'method' => 'cash',
                'allocate_to_sale_id' => $sale->id,
                'notes' => 'Partial payment against sale',
            ],
        );

        $countAfter = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        // EXACTLY 1 new entry — never 2
        $this->assertSame(
            $countBefore + 1,
            $countAfter,
            'RecordPayment+allocate must write EXACTLY 1 ledger credit, not 2'
        );

        // Balance = 1000 - 300 = 700
        $this->assertSame('700.00', $customer->fresh()->ledgerBalance());

        // The 1 entry must be a credit of 300
        $creditEntries = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->where('entry_type', LedgerEntryType::PaymentCredit->value)
            ->get();

        $this->assertCount(1, $creditEntries, 'Must be exactly 1 PaymentCredit entry');
        $this->assertSame('300.00', (string) $creditEntries->first()->credit);
    }

    /**
     * Part 0a — Test 4:
     * A return adds EXACTLY one credit (ReturnCredit) in the ledger.
     */
    public function test_return_adds_exactly_one_credit_in_ledger(): void
    {
        // Use track_stock=false to avoid stock complications
        $product = $this->createProduct(sellingPrice: '500.00');
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

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

        $sale = app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_amount' => '0.00',
        ]);

        $this->assertSame('1000.00', $customer->fresh()->ledgerBalance());

        $countBefore = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        $saleItem = $sale->items->first();

        app(ReturnSale::class)->handle(
            $sale,
            $this->user->id,
            [['sale_item_id' => $saleItem->id, 'quantity' => '1']],
            'Defective product',
        );

        $countAfter = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->count();

        // EXACTLY 1 new entry (ReturnCredit)
        $this->assertSame(
            $countBefore + 1,
            $countAfter,
            'Return must add exactly 1 ledger credit entry'
        );

        $returnCredits = CustomerLedgerEntry::where('shop_id', $this->shop->id)
            ->where('customer_id', $customer->id)
            ->where('entry_type', LedgerEntryType::ReturnCredit->value)
            ->get();

        $this->assertCount(1, $returnCredits, 'Must be exactly 1 ReturnCredit entry');
        $this->assertSame('500.00', (string) $returnCredits->first()->credit);

        // Balance = 1000 - 500 = 500
        $this->assertSame('500.00', $customer->fresh()->ledgerBalance());
    }

    /**
     * Part 0b — Test 5:
     * ledger:reconcile detects drift when a rogue ledger row is inserted via raw DB.
     * Must exit with code 1.
     */
    public function test_reconcile_detects_drift_when_ledger_row_tampered_via_raw_db(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        // Clean state — no drift
        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(0);

        // Tamper: insert a fake debit row directly bypassing all Action logic
        DB::statement(
            "INSERT INTO customer_ledger_entries
                (shop_id, customer_id, entry_type, debit, credit, notes, created_at, updated_at)
             VALUES (?, ?, 'manual_debit', 9999.00, 0.00, 'TAMPERED', NOW(), NOW())",
            [$this->shop->id, $customer->id]
        );

        // Reconcile must detect drift (exits 1)
        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('DRIFT DETECTED');
    }

    /**
     * Part 0b — Test 6:
     * ledger:reconcile detects drift when a sale's paid_total cache is tampered via raw DB.
     * Must exit with code 1.
     */
    public function test_reconcile_detects_drift_when_sale_paid_cache_tampered_via_raw_db(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        [$sale] = $this->postSaleForCustomer($customer, '500.00', '200.00');

        // Clean state — reconcile should pass
        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(0);

        // Tamper: corrupt the sale's paid_total cache directly
        DB::statement(
            'UPDATE sales SET paid_total = 999.00 WHERE id = ?',
            [$sale->id]
        );

        // Reconcile should detect the drift between cached paid_total and actual ledger credits
        $this->artisan('ledger:reconcile', ['--shop' => $this->shop->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('DRIFT DETECTED');
    }
}
