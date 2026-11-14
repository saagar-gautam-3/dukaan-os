<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

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
use App\Models\SaleItem;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\StockAdjustmentItem;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T7c: money and quantity inputs may hold huge or malformed values.
 *
 * Every boundary value must either fail validation (redirect with errors) or
 * land in the database as the exact DECIMAL(14,2)/(14,3) string. A 500 or a
 * float artefact (e.g. 0.30000000000000004) fails the check.
 */
final class MoneyQuantityBoundaryTest extends TestCase
{
    use RefreshDatabase;

    /** Devanagari 123.45 */
    private const DEVANAGARI_123_45 = "\u{0967}\u{0968}\u{0969}.\u{096A}\u{096B}";

    /** Devanagari 10.50 */
    private const DEVANAGARI_10_50 = "\u{0967}\u{0966}.\u{096B}\u{0966}";

    /** Devanagari 1.500 */
    private const DEVANAGARI_1_500 = "\u{0967}.\u{096B}\u{0966}\u{0966}";

    private Shop $shop;

    private User $owner;

    private Warehouse $warehouse;

    private Unit $unit;

    private Supplier $supplier;

    private Product $product;

    private Customer $customer;

    private ExpenseCategory $expenseCategory;

    private int $sequence = 0;

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

        $this->warehouse = Warehouse::factory()->create(['shop_id' => $this->shop->id, 'is_default' => true]);
        $this->unit = Unit::firstOrCreate(['symbol' => 'pcs'], ['name' => 'Piece', 'is_fractional' => false]);
        $category = Category::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Staples']);
        $this->supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);
        $this->product = Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $category->id,
            'sku' => 'BOUND-1',
            'cost_price' => '10.00',
            'selling_price' => '10.00',
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

    public function test_product_money_fields_reject_or_store_exact_decimals(): void
    {
        $base = [
            'name' => 'Boundary Product',
            'unit_id' => $this->unit->id,
        ];

        $cases = [
            ['cost_price' => '-1', 'selling_price' => '10', 'reject' => true],
            ['cost_price' => '0', 'selling_price' => '0', 'expected' => ['0.00', '0.00']],
            ['cost_price' => '0.001', 'selling_price' => '0.001', 'expected' => ['0.00', '0.00']],
            ['cost_price' => '999999999.99', 'selling_price' => '999999999.99', 'expected' => ['999999999.99', '999999999.99']],
            ['cost_price' => '1000000000', 'selling_price' => '1000000000', 'expected' => ['1000000000.00', '1000000000.00']],
            ['cost_price' => 'abc', 'selling_price' => '10', 'reject' => true],
            ['cost_price' => self::DEVANAGARI_123_45, 'selling_price' => self::DEVANAGARI_10_50, 'expected' => ['123.45', '10.50']],
            ['cost_price' => '10.999', 'selling_price' => '20.999', 'expected' => ['11.00', '21.00']],
            ['cost_price' => '', 'selling_price' => '10', 'reject' => true],
        ];

        foreach ($cases as $case) {
            $payload = array_merge($base, [
                'cost_price' => $case['cost_price'],
                'selling_price' => $case['selling_price'],
            ]);
            $context = 'product cost/selling '.$case['cost_price'].'/'.$case['selling_price'];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('products.store'), $payload, $context);

                continue;
            }

            $this->assertStored(route('products.store'), $payload, function (): array {
                $product = Product::latest('id')->firstOrFail();

                return [(string) $product->cost_price, (string) $product->selling_price];
            }, $case['expected'], $context);
        }
    }

    public function test_customer_credit_limit_rejects_or_stores_exact_decimals(): void
    {
        $cases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'expected' => '0.00'],
            ['value' => '0.001', 'expected' => '0.00'],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'expected' => null],
        ];

        foreach ($cases as $case) {
            $payload = ['name' => 'Boundary Customer', 'credit_limit' => $case['value']];
            $context = 'customer credit_limit '.$case['value'];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('customers.store'), $payload, $context);

                continue;
            }

            $this->assertStored(route('customers.store'), $payload, function (): ?string {
                $limit = Customer::latest('id')->firstOrFail()->credit_limit;

                return $limit === null ? null : (string) $limit;
            }, $case['expected'], $context);
        }
    }

    public function test_sale_line_quantity_and_price_reject_or_store_exact_decimals(): void
    {
        $quantityCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'reject' => true],
            ['value' => '0.001', 'expected' => '0.001'],
            ['value' => '999999999.99', 'expected' => '999999999.990'],
            ['value' => '1000000000', 'expected' => '1000000000.000'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_1_500, 'expected' => '1.500'],
            ['value' => '1.2341', 'expected' => '1.234'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($quantityCases as $case) {
            $payload = $this->salePayload([
                'quantity' => $case['value'],
                'unit_price' => '10.00',
            ]);

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('sales.store'), $payload, 'sale quantity '.$case['value']);

                continue;
            }

            $this->assertStored(route('sales.store'), $payload, fn (): string => (string) $this->latestSaleItem()->quantity, $case['expected'], 'sale quantity '.$case['value']);
        }

        $priceCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'expected' => '0.00'],
            ['value' => '0.001', 'expected' => '0.00'],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($priceCases as $case) {
            $payload = $this->salePayload([
                'quantity' => '1.000',
                'unit_price' => $case['value'],
            ]);

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('sales.store'), $payload, 'sale unit_price '.$case['value']);

                continue;
            }

            $this->assertStored(route('sales.store'), $payload, fn (): string => (string) $this->latestSaleItem()->unit_price, $case['expected'], 'sale unit_price '.$case['value']);
        }
    }

    public function test_purchase_line_quantity_and_cost_reject_or_store_exact_decimals(): void
    {
        $quantityCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'reject' => true],
            ['value' => '0.001', 'expected' => '0.001'],
            ['value' => '999999999.99', 'expected' => '999999999.990'],
            ['value' => '1000000000', 'expected' => '1000000000.000'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_1_500, 'expected' => '1.500'],
            ['value' => '1.2341', 'expected' => '1.234'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($quantityCases as $case) {
            $payload = $this->purchasePayload([
                'quantity' => $case['value'],
                'unit_cost' => '10.00',
            ]);

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('purchases.store'), $payload, 'purchase quantity '.$case['value']);

                continue;
            }

            $this->assertStored(route('purchases.store'), $payload, fn (): string => (string) $this->latestPurchaseItem()->quantity, $case['expected'], 'purchase quantity '.$case['value']);
        }

        $costCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'expected' => '0.00'],
            ['value' => '0.001', 'expected' => '0.00'],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($costCases as $case) {
            $payload = $this->purchasePayload([
                'quantity' => '1.000',
                'unit_cost' => $case['value'],
            ]);

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('purchases.store'), $payload, 'purchase unit_cost '.$case['value']);

                continue;
            }

            $this->assertStored(route('purchases.store'), $payload, fn (): string => (string) $this->latestPurchaseItem()->unit_cost, $case['expected'], 'purchase unit_cost '.$case['value']);
        }
    }

    public function test_expense_amount_rejects_or_stores_exact_decimals(): void
    {
        $cases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'reject' => true],
            ['value' => '0.001', 'reject' => true],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($cases as $case) {
            $payload = [
                'category_id' => $this->expenseCategory->id,
                'amount' => $case['value'],
                'expense_date' => now($this->shop->timezone)->toDateString(),
                'payment_method' => 'cash',
                'description' => 'Boundary expense',
            ];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('expenses.store'), $payload, 'expense amount '.$case['value']);

                continue;
            }

            $this->assertStored(route('expenses.store'), $payload, fn (): string => (string) Expense::latest('id')->firstOrFail()->amount, $case['expected'], 'expense amount '.$case['value']);
        }
    }

    public function test_stock_adjust_quantity_and_cost_reject_or_store_exact_decimals(): void
    {
        $quantityCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'reject' => true],
            ['value' => '0.001', 'expected' => '0.001'],
            ['value' => '999999999.99', 'expected' => '999999999.990'],
            ['value' => '1000000000', 'expected' => '1000000000.000'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_1_500, 'expected' => '1.500'],
            ['value' => '1.2341', 'expected' => '1.234'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($quantityCases as $case) {
            $payload = [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'type' => 'in',
                'quantity' => $case['value'],
                'reason' => 'Boundary adjustment',
                'unit_cost' => '10.00',
            ];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('stock.adjust.store'), $payload, 'stock quantity '.$case['value']);

                continue;
            }

            $this->assertStored(route('stock.adjust.store'), $payload, fn (): string => (string) StockAdjustmentItem::latest('id')->firstOrFail()->quantity, $case['expected'], 'stock quantity '.$case['value']);
        }

        $costCases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'expected' => '0.00'],
            ['value' => '0.001', 'expected' => '0.00'],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'expected' => '10.00'],
        ];

        foreach ($costCases as $case) {
            $payload = [
                'warehouse_id' => $this->warehouse->id,
                'product_id' => $this->product->id,
                'type' => 'out',
                'quantity' => '1.000',
                'reason' => 'Boundary adjustment',
                'unit_cost' => $case['value'],
            ];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('stock.adjust.store'), $payload, 'stock unit_cost '.$case['value']);

                continue;
            }

            $this->assertStored(route('stock.adjust.store'), $payload, fn (): string => (string) StockAdjustmentItem::latest('id')->firstOrFail()->unit_cost, $case['expected'], 'stock unit_cost '.$case['value']);
        }
    }

    public function test_cash_session_opening_and_closing_reject_or_store_exact_decimals(): void
    {
        $cases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'expected' => '0.00'],
            ['value' => '0.001', 'expected' => '0.00'],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($cases as $case) {
            CashSession::query()->delete();

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('cash.open'), ['opening_cash' => $case['value']], 'cash opening '.$case['value']);

                continue;
            }

            $this->assertStored(route('cash.open'), ['opening_cash' => $case['value']], fn (): string => (string) CashSession::latest('id')->firstOrFail()->opening_cash, $case['expected'], 'cash opening '.$case['value']);
        }

        foreach ($cases as $case) {
            $session = CashSession::factory()->create([
                'shop_id' => $this->shop->id,
                'opened_by' => $this->owner->id,
            ]);
            $payload = ['closing_cash' => $case['value']];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('cash.close', $session), $payload, 'cash closing '.$case['value']);

                continue;
            }

            $this->assertStored(route('cash.close', $session), $payload, fn (): string => (string) $session->fresh()->closing_cash, $case['expected'], 'cash closing '.$case['value']);
        }
    }

    public function test_udhar_collection_rejects_or_stores_exact_decimals(): void
    {
        $cases = [
            ['value' => '-1', 'reject' => true],
            ['value' => '0', 'reject' => true],
            ['value' => '0.001', 'reject' => true],
            ['value' => '999999999.99', 'expected' => '999999999.99'],
            ['value' => '1000000000', 'expected' => '1000000000.00'],
            ['value' => 'abc', 'reject' => true],
            ['value' => self::DEVANAGARI_123_45, 'expected' => '123.45'],
            ['value' => '10.999', 'expected' => '11.00'],
            ['value' => '', 'reject' => true],
        ];

        foreach ($cases as $case) {
            $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
            CustomerLedgerEntry::create([
                'shop_id' => $this->shop->id,
                'customer_id' => $customer->id,
                'entry_type' => LedgerEntryType::ManualDebit->value,
                'debit' => '1000000000.00',
                'credit' => '0.00',
                'created_by' => $this->owner->id,
            ]);

            $payload = [
                'amount' => $case['value'],
                'payment_method' => 'cash',
                'notes' => 'Boundary collection',
            ];

            if ($case['reject'] ?? false) {
                $this->assertRejected(route('udhar.collect.store', $customer), $payload, 'udhar amount '.$case['value']);

                continue;
            }

            $this->assertStored(route('udhar.collect.store', $customer), $payload, function () use ($customer): string {
                return (string) Payment::where('customer_id', $customer->id)->latest('id')->firstOrFail()->amount;
            }, $case['expected'], 'udhar amount '.$case['value']);
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function assertRejected(string $url, array $payload, string $context): void
    {
        $response = $this->post($url, $payload);

        $this->assertLessThan(500, $response->getStatusCode(), $context.' caused a server error.');
        $this->assertSame(302, $response->getStatusCode(), $context.' should redirect back with errors.');
        $response->assertSessionHasErrors();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @param  callable(): mixed  $stored
     */
    private function assertStored(string $url, array $payload, callable $stored, mixed $expected, string $context): void
    {
        $response = $this->post($url, $payload);

        $this->assertLessThan(500, $response->getStatusCode(), $context.' caused a server error.');
        $this->assertSame(302, $response->getStatusCode(), $context.' should redirect after success.');
        $response->assertSessionHasNoErrors();

        TenantContext::setShop($this->shop);
        $this->assertSame($expected, $stored(), $context.' was not stored as an exact decimal string.');
    }

    /**
     * @param  array<string, string>  $line
     * @return array<string, mixed>
     */
    private function salePayload(array $line): array
    {
        return [
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'post_immediately' => false,
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $line['quantity'],
                'unit_price' => $line['unit_price'],
            ]],
        ];
    }

    private function latestSaleItem(): SaleItem
    {
        $sale = Sale::latest('id')->firstOrFail();

        return SaleItem::where('sale_id', $sale->id)->firstOrFail();
    }

    /**
     * @param  array<string, string>  $line
     * @return array<string, mixed>
     */
    private function purchasePayload(array $line): array
    {
        $this->sequence++;

        return [
            'supplier_id' => $this->supplier->id,
            'warehouse_id' => $this->warehouse->id,
            'purchase_no' => 'BOUND-PO-'.$this->sequence,
            'purchase_date' => now($this->shop->timezone)->toDateString(),
            'items' => [[
                'product_id' => $this->product->id,
                'quantity' => $line['quantity'],
                'unit_cost' => $line['unit_cost'],
            ]],
        ];
    }

    private function latestPurchaseItem(): PurchaseItem
    {
        $purchase = Purchase::latest('id')->firstOrFail();

        return PurchaseItem::where('purchase_id', $purchase->id)->firstOrFail();
    }
}
