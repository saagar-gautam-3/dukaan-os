<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Actions\Sales\ArchiveCustomer;
use App\Actions\Sales\CreateSale;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Part 0f: CustomerArchiveTest
 *
 * Rules:
 *  - Customer WITH sales/payments/ledger entries → archived (soft-deleted), history preserved
 *  - Customer with NO history → force-deleted from DB
 *  - Archived customer balance and ledger entries remain accessible via withTrashed
 */
final class CustomerArchiveTest extends TestCase
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

    /**
     * A customer with ledger entries must be soft-deleted (archived), not hard-deleted.
     * The ledger entries must still exist in the DB.
     */
    public function test_customer_with_ledger_entries_is_soft_deleted_not_hard_deleted(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        // Create a ledger entry directly (fastest path)
        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::SaleDebit->value,
            'debit' => '500.00',
            'credit' => '0.00',
        ]);

        $customerId = $customer->id;

        $result = app(ArchiveCustomer::class)->handle($customer);

        // Action reports it was archived (soft-deleted)
        $this->assertTrue($result['archived'], 'ArchiveCustomer should return archived=true');

        // Customer must NOT be permanently deleted
        $this->assertDatabaseHas('customers', ['id' => $customerId]);

        // Customer must have a non-null deleted_at
        $trashed = Customer::withTrashed()->find($customerId);
        $this->assertNotNull($trashed, 'Customer must still exist (trashed)');
        $this->assertNotNull($trashed->deleted_at, 'deleted_at must be set for archived customer');

        // Customer is not visible via normal query (soft-deleted)
        $this->assertNull(Customer::find($customerId), 'Archived customer must not appear in normal queries');

        // Ledger entries must still exist
        $this->assertDatabaseHas('customer_ledger_entries', [
            'customer_id' => $customerId,
            'debit' => '500.00',
        ]);
    }

    /**
     * A customer with sales must be soft-deleted (archived).
     * The ledger balance must remain intact and visible via withTrashed.
     */
    public function test_archived_customer_balance_and_history_remain_visible(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $customerId = $customer->id;

        // Add ledger entries
        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::SaleDebit->value,
            'debit' => '1500.00',
            'credit' => '0.00',
        ]);

        CustomerLedgerEntry::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => LedgerEntryType::PaymentCredit->value,
            'debit' => '0.00',
            'credit' => '500.00',
        ]);

        app(ArchiveCustomer::class)->handle($customer);

        // Trashed customer's ledger entries are still accessible
        $trashed = Customer::withTrashed()->find($customerId);
        $this->assertNotNull($trashed);

        // Balance computed from ledger entries remains
        $balance = $trashed->ledgerEntries()->sum('debit') - $trashed->ledgerEntries()->sum('credit');
        $this->assertEqualsWithDelta(1000.00, $balance, 0.001, 'Balance must be 1500 - 500 = 1000');

        // Entries still in DB
        $this->assertDatabaseCount('customer_ledger_entries', 2);
    }

    /**
     * A customer with NO sales, payments, or ledger entries can be force-deleted.
     */
    public function test_customer_with_no_history_is_force_deleted(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $customerId = $customer->id;

        // Verify no history
        $this->assertFalse($customer->sales()->exists());
        $this->assertFalse($customer->payments()->exists());
        $this->assertFalse($customer->ledgerEntries()->exists());

        $result = app(ArchiveCustomer::class)->handle($customer);

        // Should be removed entirely
        $this->assertFalse($result['archived'], 'ArchiveCustomer must return archived=false for clean customer');

        // Not in DB at all (not even as trashed)
        $this->assertNull(Customer::withTrashed()->find($customerId), 'Customer must be completely removed from DB');
        $this->assertDatabaseMissing('customers', ['id' => $customerId]);
    }

    /**
     * A customer that has payments (but no sales or ledger) must still be soft-deleted.
     */
    public function test_customer_with_payments_only_is_archived_not_deleted(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $customerId = $customer->id;

        // Create a payment directly
        Payment::factory()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'amount' => '200.00',
            'method' => 'cash',
            'status' => 'verified',
        ]);

        $result = app(ArchiveCustomer::class)->handle($customer);

        $this->assertTrue($result['archived']);
        $this->assertNotNull(Customer::withTrashed()->find($customerId)?->deleted_at);
    }

    /**
     * A customer with a sale (even without ledger entries) must be soft-deleted.
     */
    public function test_customer_with_sales_is_archived_not_deleted(): void
    {
        $product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'cost_price' => '100.00',
            'selling_price' => '200.00',
            'track_stock' => false,
        ]);

        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $customerId = $customer->id;

        // Create a sale (draft is enough to establish history)
        app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '200.00'],
            ],
        ], $this->user->id);

        $result = app(ArchiveCustomer::class)->handle($customer);

        $this->assertTrue($result['archived']);
        $this->assertNotNull(Customer::withTrashed()->find($customerId)?->deleted_at);
    }
}
