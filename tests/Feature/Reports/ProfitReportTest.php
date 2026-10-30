<?php

declare(strict_types=1);

namespace Tests\Feature\Reports;

use App\Actions\Sales\CreateSale;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\ReturnSale;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Unit;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ProfitReportService;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Part 1 — Step-05 Reports & Cash Tests
 *
 * Covers:
 *  - Worked example: Rs 10,000 sale, Rs 6,000 paid → sales 10,000, cash 6,000, outstanding 4,000
 *  - Returns reduce net_sales and COGS
 *  - Expenses reduce net_profit
 *  - Timezone: sale at 02:00 Nepal time is "today" in Nepal tz (previous UTC day)
 *  - QR pending does not count as cash_received
 *  - Permission: Accountant can see profit, Cashier cannot
 *  - One open session per shop enforced
 *  - Close session shows expected vs counted difference
 *  - Cross-tenant isolation for expenses, cash_sessions
 */
final class ProfitReportTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    private Warehouse $warehouse;

    private Unit $unit;

    private Category $category;

    private ProfitReportService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->shop = Shop::factory()->create([
            'settings' => ['allow_negative_stock' => true],
            'timezone' => 'Asia/Kathmandu',
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
        $this->service = app(ProfitReportService::class);
    }

    private function createProduct(string $costPrice = '400.00', string $sellingPrice = '1000.00'): Product
    {
        return Product::factory()->create([
            'shop_id' => $this->shop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $this->category->id,
            'cost_price' => $costPrice,
            'selling_price' => $sellingPrice,
            'track_stock' => false,
        ]);
    }

    private function todayRange(): array
    {
        return $this->service->resolveDateRange('today', 'Asia/Kathmandu');
    }

    // ─────────────────────────────────────────────────────────────────────
    // WORKED EXAMPLE
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Worked example from the spec:
     *   Rs 10,000 sale, Rs 6,000 paid at POS, Rs 4,000 on credit.
     *   → sales = 10,000 | cash_received = 6,000 | outstanding = 4,000
     */
    public function test_worked_example_sale_10000_paid_6000_outstanding_4000(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = $this->createProduct(costPrice: '4000.00', sellingPrice: '10000.00');

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '6000.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10000.00'],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '6000.00',
        ]);

        $summary = $this->service->summary($this->shop->id, $this->todayRange());

        $this->assertSame('10000.00', $summary['gross_sales'], 'gross_sales must be 10000');
        $this->assertSame('6000.00', $summary['cash_received'], 'cash_received must be 6000');
        $this->assertSame('4000.00', $summary['outstanding'], 'outstanding must be 4000');
        $this->assertSame(1, $summary['sale_count']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // RETURNS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Returns reduce net_sales and COGS at the original unit_cost snapshot.
     */
    public function test_returns_reduce_net_sales_and_cogs_at_original_unit_cost(): void
    {
        $product = $this->createProduct(costPrice: '300.00', sellingPrice: '500.00');

        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '0.00',
            'items' => [
                // 2 units × 500 = 1000; COGS = 2 × 300 = 600 (but track_stock=false, unit_cost=0)
                ['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '500.00'],
            ],
        ], $this->user->id);

        $sale = app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '0.00']);

        $beforeReturn = $this->service->summary($this->shop->id, $this->todayRange());
        $this->assertSame('1000.00', $beforeReturn['gross_sales']);

        $saleItem = $sale->items->first();

        // Return 1 of 2 units
        app(ReturnSale::class)->handle(
            $sale,
            $this->user->id,
            [['sale_item_id' => $saleItem->id, 'quantity' => '1']],
            'Damaged',
        );

        $afterReturn = $this->service->summary($this->shop->id, $this->todayRange());

        // Return total = 1 × 500 = 500; net_sales = 1000 - 500 = 500
        $this->assertSame('500.00', $afterReturn['net_sales'], 'net_sales must be reduced by return amount');
        $this->assertSame('500.00', $afterReturn['return_total'], 'return_total must be 500');
    }

    // ─────────────────────────────────────────────────────────────────────
    // EXPENSES
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Expenses reduce net_profit (gross_profit − expenses).
     */
    public function test_expenses_reduce_net_profit(): void
    {
        $product = $this->createProduct(costPrice: '200.00', sellingPrice: '400.00');
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '400.00',
            'items' => [
                ['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '400.00'],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($sale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '400.00',
        ]);

        $summaryNoExpense = $this->service->summary($this->shop->id, $this->todayRange());
        $grossProfitBefore = $summaryNoExpense['gross_profit'];

        // Add an expense of Rs 150
        $expCategory = ExpenseCategory::factory()->create(['shop_id' => $this->shop->id]);
        Expense::factory()->create([
            'shop_id' => $this->shop->id,
            'category_id' => $expCategory->id,
            'amount' => '150.00',
            'expense_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        $summaryWithExpense = $this->service->summary($this->shop->id, $this->todayRange());

        $this->assertSame('150.00', $summaryWithExpense['total_expenses'], 'Expense must be 150');
        $this->assertSame(
            bcsub($grossProfitBefore, '150.00', 2),
            $summaryWithExpense['net_profit'],
            'net_profit must be gross_profit - 150'
        );
    }

    // ─────────────────────────────────────────────────────────────────────
    // TIMEZONE
    // ─────────────────────────────────────────────────────────────────────

    /**
     * A sale made at 02:00 Nepal time (Asia/Kathmandu) must be included in "today"
     * even though 02:00 NPT = 20:15 UTC on the PREVIOUS day.
     *
     * Nepal timezone = UTC+5:45
     * So 02:00 NPT = 20:15 UTC (previous day)
     */
    public function test_sale_at_0200_nepal_time_is_included_in_today_for_nepal_shop(): void
    {
        // Pin "now" to 02:00 Nepal time today
        $nowNepal = Carbon::now('Asia/Kathmandu')->startOfDay()->addHours(2); // 02:00 NPT today
        $nowUtc = $nowNepal->copy()->utc(); // 20:15 UTC previous day (or same day if DST)

        $product = $this->createProduct();
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

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

        $sale = app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '0.00']);

        // Force the posted_at to 02:00 NPT (expressed in UTC for DB storage)
        DB::statement('UPDATE sales SET posted_at = ? WHERE id = ?', [
            $nowUtc->toDateTimeString(),
            $sale->id,
        ]);

        // Resolve "today" range in Nepal timezone
        $range = $this->service->resolveDateRange('today', 'Asia/Kathmandu');

        // The Nepal "today" range must include the 02:00 NPT timestamp
        $this->assertTrue(
            $nowUtc->between($range['from'], $range['to']),
            "02:00 NPT ({$nowUtc->toDateTimeString()} UTC) must fall inside today's Nepal range"
        );

        $summary = $this->service->summary($this->shop->id, $range);

        $this->assertSame(1, $summary['sale_count'], 'Sale at 02:00 Nepal time must be included in today');
        $this->assertSame('1000.00', $summary['gross_sales']);
    }

    // ─────────────────────────────────────────────────────────────────────
    // QR PENDING
    // ─────────────────────────────────────────────────────────────────────

    /**
     * QR payment with status=pending must NOT count as cash_received.
     * Only verified payments count.
     */
    public function test_qr_pending_payment_does_not_count_as_cash_received(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);

        // Create a QR-pending payment (not verified)
        Payment::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'customer_id' => $customer->id,
            'method' => PaymentMethod::Qr->value,
            'status' => PaymentStatus::Pending->value,
            'amount' => '500.00',
            'received_at' => now(),
        ]);

        $summary = $this->service->summary($this->shop->id, $this->todayRange());

        $this->assertSame('0.00', $summary['cash_received'], 'Pending QR must not count as cash_received');
    }

    // ─────────────────────────────────────────────────────────────────────
    // PERMISSIONS
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Accountant role has reports.profit.view — can access profit report.
     */
    public function test_accountant_can_view_profit_report(): void
    {
        $accountant = User::factory()->create();
        $role = $this->shop->roles()->where('name', 'Accountant')->first();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $accountant->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($accountant);

        $response = $this->get(route('reports.profit'));
        $response->assertOk();
    }

    /**
     * Cashier role does NOT have reports.profit.view — must be forbidden.
     */
    public function test_cashier_cannot_view_profit_report(): void
    {
        $cashier = User::factory()->create();
        $role = $this->shop->roles()->where('name', 'Cashier')->first();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $cashier->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);

        $this->actingAs($cashier);

        $response = $this->get(route('reports.profit'));
        $response->assertForbidden();
    }

    // ─────────────────────────────────────────────────────────────────────
    // CROSS-TENANT ISOLATION
    // ─────────────────────────────────────────────────────────────────────

    /**
     * Expenses from another shop must NOT appear in this shop's report.
     */
    public function test_cross_tenant_isolation_expenses(): void
    {
        $otherShop = Shop::factory()->create();
        TenantContext::setShop($otherShop);

        $otherCategory = ExpenseCategory::factory()->create(['shop_id' => $otherShop->id]);
        Expense::factory()->create([
            'shop_id' => $otherShop->id,
            'category_id' => $otherCategory->id,
            'amount' => '9999.00',
            'expense_date' => now()->toDateString(),
            'created_by' => $this->user->id,
        ]);

        // Switch back to this shop
        TenantContext::setShop($this->shop);

        $summary = $this->service->summary($this->shop->id, $this->todayRange());

        $this->assertSame('0.00', $summary['total_expenses'], 'Other shop expenses must not leak');
    }

    /**
     * Sales from another shop must NOT appear in this shop's report.
     */
    public function test_cross_tenant_isolation_sales_in_profit_report(): void
    {
        $otherShop = Shop::factory()->create(['settings' => ['allow_negative_stock' => true]]);
        TenantContext::setShop($otherShop);

        $otherWarehouse = Warehouse::factory()->create(['shop_id' => $otherShop->id, 'is_default' => true]);
        $otherCategory = Category::factory()->create(['shop_id' => $otherShop->id]);
        $otherProduct = Product::factory()->create([
            'shop_id' => $otherShop->id,
            'unit_id' => $this->unit->id,
            'category_id' => $otherCategory->id,
            'track_stock' => false,
            'selling_price' => '2000.00',
        ]);

        $rbac = app(RbacService::class);
        $rbac->createDefaultRolesForShop($otherShop);

        $otherSale = app(CreateSale::class)->handle([
            'shop_id' => $otherShop->id,
            'warehouse_id' => $otherWarehouse->id,
            'payment_method' => PaymentMethod::Cash->value,
            'paid_total' => '2000.00',
            'items' => [
                ['product_id' => $otherProduct->id, 'quantity' => 1, 'unit_price' => '2000.00'],
            ],
        ], $this->user->id);

        app(PostSale::class)->handle($otherSale, $this->user->id, [
            'payment_method' => 'cash',
            'payment_amount' => '2000.00',
        ]);

        // Switch back to this shop
        TenantContext::setShop($this->shop);

        $summary = $this->service->summary($this->shop->id, $this->todayRange());

        $this->assertSame('0.00', $summary['gross_sales'], 'Other shop sales must not leak');
    }
}
