<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Reports\QueryDailySummary;
use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DailySummaryTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create(['timezone' => 'Asia/Kathmandu']);
        TenantContext::setShop($this->shop);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_exact_totals_and_sales_split(): void
    {
        [$warehouse, $customer, $today] = $this->fixtures();
        $product = $this->product($warehouse);

        // Sale A: posted at the very end of the local day.
        $saleA = $this->postedSale($product, $customer, '1000.00', '1000.00', '950.00', $today.' 23:59:59');
        $this->saleItems($saleA, $product, '2.000', '475.00', '400.00');

        // Sale B: posted exactly at next-day midnight — excluded.
        $this->postedSale($product, $customer, '700.00', '700.00', '700.00', Carbon::parse($today, 'Asia/Kathmandu')->addDay()->toDateString().' 00:00:00');

        // Sale C: posted at yesterday's last second — excluded.
        $this->postedSale($product, $customer, '300.00', '300.00', '300.00', Carbon::parse($today, 'Asia/Kathmandu')->subDay()->toDateString().' 23:59:59');

        // Draft and cancelled posted today but not posted — excluded.
        Sale::factory()->draft()->create(['shop_id' => $this->shop->id, 'posted_at' => $today.' 10:00:00']);
        Sale::factory()->create(['shop_id' => $this->shop->id, 'status' => SaleStatus::Cancelled, 'posted_at' => $today.' 10:00:00']);

        // Sale D (partial credit): posted today, 300 paid by cash at the POS.
        $saleD = $this->postedSale($product, $customer, '800.00', '800.00', '300.00', $today.' 09:00:00');
        $this->saleItems($saleD, $product, '5.000', '160.00', '100.00');
        $this->allocate($saleD, 'cash', '300.00', $today.' 09:00:00');

        // Walk-in full-cash sale E.
        $saleE = $this->postedSale($product, null, '500.00', '500.00', '500.00', $today.' 11:00:00');
        $this->allocate($saleE, 'cash', '500.00', $today.' 11:00:00');

        // Digital payment for sale A (bank, verified).
        $this->allocate($saleA, 'bank', '950.00', $today.' 23:59:59');

        // QR pending payment must not count anywhere.
        $pending = $this->allocate($saleA, 'qr', '50.00', $today.' 23:59:59', PaymentStatus::Pending->value);
        $this->assertSame(PaymentStatus::Pending, $pending->status);

        // Completed return today.
        SaleReturn::create([
            'shop_id' => $this->shop->id,
            'sale_id' => $saleA->id,
            'return_no' => 'RET-TEST-1',
            'status' => ReturnStatus::Completed->value,
            'total' => '100.00',
            'created_at' => Carbon::parse($today.' 12:00:00', 'Asia/Kathmandu')->utc()->toDateTimeString(),
            'updated_at' => Carbon::parse($today.' 12:00:00', 'Asia/Kathmandu')->utc()->toDateTimeString(),
        ]);

        $summary = app(QueryDailySummary::class)->handle($this->shop, $today);

        $this->assertSame(3, $summary['sales']['bill_count']);
        $this->assertSame('2300.00', $summary['sales']['total']);
        $this->assertSame('800.00', $summary['sales']['cash']);
        $this->assertSame('950.00', $summary['sales']['digital']);
        $this->assertSame('550.00', $summary['sales']['credit']);
        $this->assertSame(1, $summary['sales']['returns_count']);
        $this->assertSame('100.00', $summary['sales']['returns_amount']);
        $this->assertSame('2200.00', $summary['sales']['net_sales']);
        // COGS: sale A (2 x 400) + sale D (5 x 100) = 1300; net 2200 - 1300 = 900.
        $this->assertSame('900.00', $summary['sales']['gross_profit']);
        $this->assertSame($product->name, $summary['top_products'][0]['name']);
        $this->assertSame('7.000', $summary['top_products'][0]['qty']);
        $this->assertSame('1750.00', $summary['top_products'][0]['amount']);
    }

    public function test_udhar_invariant_with_returns_and_advance(): void
    {
        [, $customer, $today] = $this->fixtures();
        $product = $this->product(Warehouse::first());

        // Outstanding from yesterday: a prior sale posted at 23:59:59 for 500.
        $yesterday = Carbon::parse($today, 'Asia/Kathmandu')->subDay()->toDateString();
        $oldSale = $this->postedSale($product, $customer, '500.00', '500.00', '0.00', $yesterday.' 18:00:00');
        $this->ledger($customer, LedgerEntryType::SaleDebit, '500.00', '0.00', 'sale', $oldSale->id, $yesterday.' 18:00:00');

        // Today's partial-credit sale: 800 grand, 300 paid at POS.
        $sale = $this->postedSale($product, $customer, '800.00', '800.00', '300.00', $today.' 09:00:00');
        $this->ledger($customer, LedgerEntryType::SaleDebit, '800.00', '0.00', 'sale', $sale->id, $today.' 09:00:00');
        $this->ledger($customer, LedgerEntryType::PaymentCredit, '0.00', '300.00', 'sale', $sale->id, $today.' 09:00:00');

        // Today's collection: 200 against the old sale + 50 advance.
        $this->ledger($customer, LedgerEntryType::PaymentCredit, '0.00', '200.00', 'payment', 999, $today.' 13:00:00');
        $this->ledger($customer, LedgerEntryType::AdvanceCredit, '0.00', '50.00', 'payment', 1000, $today.' 13:05:00');

        // Today's return on the old sale: reduces the receivable.
        $this->ledger($customer, LedgerEntryType::ReturnCredit, '0.00', '60.00', 'sale_return', 555, $today.' 14:00:00');

        $summary = app(QueryDailySummary::class)->handle($this->shop, $today)['udhar'];

        // given = 800 - 300 (at-pos paid part nets against the sale) = 500
        $this->assertSame('500.00', $summary['given']);
        // collected = 200 (not tied to today's sales) + 50 advance = 250
        $this->assertSame('250.00', $summary['collected']);
        $this->assertSame('60.00', $summary['returns']);

        // Invariant: receiving end-start = given - collected - returns.
        $visibleStart = (string) $summary['receivable_start'];
        $visibleEnd = (string) $summary['receivable_now'];
        $this->assertSame(
            Money::subtract($summary['given'], Money::add($summary['collected'], $summary['returns'], 2), 2),
            Money::subtract($visibleEnd, $visibleStart, 2)
        );
        $this->assertSame('500.00', $visibleStart);
        $this->assertSame('690.00', $visibleEnd);
    }

    public function test_expenses_purchases_cash_and_stock_sections(): void
    {
        [, $customer, $today] = $this->fixtures();

        $catA = $this->expenseCategory('Rent');
        $catB = $this->expenseCategory('Fuel');
        $catC = $this->expenseCategory('Food');
        $catD = $this->expenseCategory('Misc');
        $user = User::factory()->create();

        Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $catA->id, 'amount' => '500.00', 'expense_date' => $today, 'payment_method' => 'cash', 'created_by' => $user->id]);
        Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $catB->id, 'amount' => '300.00', 'expense_date' => $today, 'payment_method' => 'bank', 'created_by' => $user->id]);
        Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $catC->id, 'amount' => '200.00', 'expense_date' => $today, 'payment_method' => 'cash', 'created_by' => $user->id]);
        Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $catD->id, 'amount' => '100.00', 'expense_date' => $today, 'payment_method' => 'cash', 'created_by' => $user->id]);
        Expense::factory()->create(['shop_id' => $this->shop->id, 'category_id' => $catA->id, 'amount' => '900.00', 'expense_date' => Carbon::parse($today)->subDay()->toDateString(), 'created_by' => $user->id]);

        $supplier = $this->supplier();
        $purchase = Purchase::factory()->create(['shop_id' => $this->shop->id, 'supplier_id' => $supplier->id, 'status' => PurchaseStatus::Posted->value, 'grand_total' => '2000.00', 'purchase_date' => $today, 'posted_at' => $today.' 08:00:00', 'created_by' => $user->id]);
        $this->assertSame(PurchaseStatus::Posted, $purchase->status);
        Purchase::factory()->create(['shop_id' => $this->shop->id, 'status' => PurchaseStatus::Draft->value, 'grand_total' => '777.00', 'purchase_date' => $today]);

        $session = CashSession::factory()->create(['shop_id' => $this->shop->id, 'status' => CashSessionStatus::Open->value, 'opening_cash' => '1000.00', 'opened_at' => $today.' 08:00:00']);
        $this->movement($session, CashMovementType::SaleCash, '500.00', $today.' 10:00:00');
        $this->movement($session, CashMovementType::ExpenseCash, '100.00', $today.' 11:00:00');
        $this->movement($session, CashMovementType::CashOut, '50.00', $today.' 12:00:00');
        // Movements outside the drawer session window still use created_at for the day.
        $summary = app(QueryDailySummary::class)->handle($this->shop, $today);

        $this->assertSame('1100.00', $summary['expenses']['total']);
        $this->assertSame(['Rent', 'Fuel', 'Food'], array_column($summary['expenses']['top_categories'], 'name'));
        $this->assertSame('2000.00', $summary['purchases']['total']);
        $this->assertSame('500.00', $summary['cash']['in']);
        $this->assertSame('150.00', $summary['cash']['out']);
        $this->assertSame('350.00', $summary['cash']['net']);
        $this->assertSame('1350.00', $summary['cash']['open_drawer_balance']);
    }

    public function test_other_shop_data_is_never_counted_and_empty_day_is_zeros(): void
    {
        [, , $today] = $this->fixtures();
        $otherShop = Shop::factory()->create(['timezone' => 'Asia/Kathmandu']);
        $otherWarehouse = Warehouse::factory()->create(['shop_id' => $otherShop->id]);
        $otherProduct = $this->product($otherWarehouse);

        $this->postedSale($otherProduct, null, '9999.00', '9999.00', '9999.00', $today.' 10:00:00');

        // An empty future day within the shop must return zeros (no division by zero).
        $future = Carbon::parse($today)->addDays(5)->toDateString();
        $summary = app(QueryDailySummary::class)->handle($this->shop, $future);

        $this->assertSame(0, $summary['sales']['bill_count']);
        $this->assertSame('0.00', $summary['sales']['total']);
        $this->assertSame('0.00', $summary['sales']['cash']);
        $this->assertSame(0, $summary['sales']['returns_count']);
        $this->assertSame('0.00', $summary['expenses']['total']);
        $this->assertSame([], $summary['expenses']['top_categories']);
        $this->assertSame('0.00', $summary['purchases']['total']);
        $this->assertSame([], $summary['top_products']);
        $this->assertSame('0.00', $summary['cash']['in']);
        $this->assertNull($summary['cash']['open_drawer_balance']);
    }

    private function fixtures(): array
    {
        $warehouse = Warehouse::factory()->create(['shop_id' => $this->shop->id, 'is_default' => true]);
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id, 'name' => 'Sita', 'phone' => '9800000001']);

        return [$warehouse, $customer, Carbon::now('Asia/Kathmandu')->toDateString()];
    }

    private function product(Warehouse $warehouse): Product
    {
        $unit = Unit::factory()->create(['is_fractional' => true]);

        return Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $unit->id,
            'low_stock_threshold' => '5.000',
            'track_stock' => true,
        ]);
    }

    private function postedSale(Product $product, ?Customer $customer, string $subtotal, string $grandTotal, string $paidTotal, string $postedAtLocal): Sale
    {
        $postedAtUtc = Carbon::parse($postedAtLocal, 'Asia/Kathmandu')->utc()->toDateTimeString();

        return Sale::factory()->posted()->create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer?->id,
            'subtotal' => $subtotal,
            'grand_total' => $grandTotal,
            'paid_total' => $paidTotal,
            'posted_at' => $postedAtUtc,
        ]);
    }

    private function saleItems(Sale $sale, Product $product, string $quantity, string $unitPrice, string $unitCost): void
    {
        SaleItem::create([
            'shop_id' => $this->shop->id,
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => $quantity,
            'unit_price' => $unitPrice,
            'unit_cost' => $unitCost,
            'line_total' => Money::multiply($unitPrice, $quantity, 2),
        ]);
    }

    private function allocate(Sale $sale, string $method, string $amount, string $receivedAtLocal, string $status = PaymentStatus::Verified->value): Payment
    {
        $payment = Payment::create([
            'shop_id' => $this->shop->id,
            'user_id' => User::factory()->create()->id,
            'customer_id' => $sale->customer_id,
            'payment_no' => 'PAY-'.uniqid(),
            'method' => $method,
            'status' => $status,
            'amount' => $amount,
            'received_at' => Carbon::parse($receivedAtLocal, 'Asia/Kathmandu')->utc()->toDateTimeString(),
        ]);

        $payment->allocations()->create([
            'shop_id' => $this->shop->id,
            'sale_id' => $sale->id,
            'amount' => $amount,
        ]);

        return $payment;
    }

    private function ledger(Customer $customer, $entryType, string $debit, string $credit, string $referenceType, int $referenceId, string $createdAtLocal): void
    {
        CustomerLedgerEntry::unguarded(fn () => CustomerLedgerEntry::create([
            'shop_id' => $this->shop->id,
            'customer_id' => $customer->id,
            'entry_type' => $entryType->value,
            'debit' => $debit,
            'credit' => $credit,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'created_at' => Carbon::parse($createdAtLocal, 'Asia/Kathmandu')->utc()->toDateTimeString(),
        ]));
    }

    private function expenseCategory(string $name): ExpenseCategory
    {
        return ExpenseCategory::factory()->create(['shop_id' => $this->shop->id, 'name' => $name]);
    }

    private function supplier()
    {
        TenantContext::runAs($this->shop, fn () => Supplier::factory()->create(['shop_id' => $this->shop->id]));

        return Supplier::query()->where('shop_id', $this->shop->id)->latest('id')->first();
    }

    private function movement(CashSession $session, $type, string $amount, string $createdAtLocal): void
    {
        CashMovement::create([
            'shop_id' => $this->shop->id,
            'cash_session_id' => $session->id,
            'type' => $type->value,
            'amount' => $amount,
            'created_at' => Carbon::parse($createdAtLocal, 'Asia/Kathmandu')->utc()->toDateTimeString(),
        ]);
    }
}
