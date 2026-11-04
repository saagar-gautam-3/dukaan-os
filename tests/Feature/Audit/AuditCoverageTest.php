<?php

declare(strict_types=1);

namespace Tests\Feature\Audit;

use App\Actions\Cash\CloseCashSession;
use App\Actions\Cash\OpenCashSession;
use App\Actions\Expenses\CreateExpense;
use App\Actions\Expenses\DeleteExpense;
use App\Actions\Expenses\UpdateExpense;
use App\Actions\Inventory\PerformStockAdjustment;
use App\Actions\Inventory\PostPurchase;
use App\Actions\Product\UpdateProduct;
use App\Actions\Sales\CreateSale;
use App\Actions\Sales\FailPayment;
use App\Actions\Sales\PostSale;
use App\Actions\Sales\RecordPayment;
use App\Actions\Sales\ReturnSale;
use App\Actions\Sales\VerifyPayment;
use App\Actions\Staff\AddStaffMember;
use App\Actions\Staff\ChangeStaffRole;
use App\Actions\Staff\RemoveStaffMember;
use App\Actions\Udhar\CollectPayment;
use App\Enums\PaymentMethod;
use App\Enums\PurchaseStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\ExpenseCategory;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Role;
use App\Models\Shop;
use App\Models\ShopUser;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\RbacService;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AuditCoverageTest extends TestCase
{
    use RefreshDatabase;

    private Shop $shop;

    private User $user;

    private Warehouse $warehouse;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shop = Shop::factory()->create();
        TenantContext::setShop($this->shop);
        $rbac = app(RbacService::class);
        $roles = $rbac->createDefaultRolesForShop($this->shop);
        $this->user = User::factory()->create();
        ShopUser::factory()->create([
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'role_id' => $roles['Shop Owner']->id,
            'status' => 'active',
        ]);
        $this->warehouse = Warehouse::factory()->create(['shop_id' => $this->shop->id]);
        $this->actingAs($this->user);
        TenantContext::setShop($this->shop);
    }

    protected function tearDown(): void
    {
        TenantContext::reset();
        parent::tearDown();
    }

    public function test_post_sale_writes_audit(): void
    {
        $product = Product::factory()->create(['shop_id' => $this->shop->id, 'track_stock' => false]);
        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'paid_total' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00']],
        ], $this->user->id);
        $before = AuditLog::count();
        app(PostSale::class)->handle($sale, $this->user->id, []);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_record_payment_writes_audit(): void
    {
        $before = AuditLog::count();
        app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Cash->value,
            'amount' => '10.00',
        ]);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_verify_and_fail_payment_write_audit(): void
    {
        $payment = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '10.00',
        ]);
        $before = AuditLog::count();
        app(VerifyPayment::class)->handle($payment, $this->user->id);
        $this->assertSame($before + 1, AuditLog::count());

        $payment2 = app(RecordPayment::class)->handle($this->user->id, [
            'method' => PaymentMethod::Qr->value,
            'amount' => '10.00',
        ]);
        $before2 = AuditLog::count();
        app(FailPayment::class)->handle($payment2, $this->user->id);
        $this->assertSame($before2 + 1, AuditLog::count());
    }

    public function test_return_sale_writes_audit(): void
    {
        $product = Product::factory()->create(['shop_id' => $this->shop->id, 'track_stock' => false]);
        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'payment_method' => 'cash',
            'paid_total' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => 2, 'unit_price' => '10.00']],
        ], $this->user->id);
        $sale = app(PostSale::class)->handle($sale, $this->user->id, []);
        $before = AuditLog::count();
        app(ReturnSale::class)->handle($sale, $this->user->id, [
            ['sale_item_id' => $sale->items->first()->id, 'quantity' => 1],
        ], 'test');
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_collect_payment_writes_audit(): void
    {
        $customer = Customer::factory()->create(['shop_id' => $this->shop->id]);
        $product = Product::factory()->create(['shop_id' => $this->shop->id, 'track_stock' => false]);
        $sale = app(CreateSale::class)->handle([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $customer->id,
            'payment_method' => 'cash',
            'paid_total' => '0.00',
            'items' => [['product_id' => $product->id, 'quantity' => 1, 'unit_price' => '10.00']],
        ], $this->user->id);
        app(PostSale::class)->handle($sale, $this->user->id, ['payment_amount' => '0.00']);
        $before = AuditLog::count();
        app(CollectPayment::class)->handle($customer, $this->user->id, [
            'amount' => '5.00',
            'method' => 'cash',
        ]);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_stock_adjustment_writes_audit(): void
    {
        $product = Product::factory()->create(['shop_id' => $this->shop->id]);
        $before = AuditLog::count();
        app(PerformStockAdjustment::class)->handle([
            'warehouse_id' => $this->warehouse->id,
            'product_id' => $product->id,
            'type' => 'in',
            'quantity' => '2.000',
            'reason' => 'test',
        ], $this->user->id);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_post_purchase_writes_audit(): void
    {
        $supplier = Supplier::factory()->create(['shop_id' => $this->shop->id]);
        $product = Product::factory()->create(['shop_id' => $this->shop->id]);
        $purchase = Purchase::factory()->create([
            'shop_id' => $this->shop->id,
            'warehouse_id' => $this->warehouse->id,
            'supplier_id' => $supplier->id,
            'status' => PurchaseStatus::Draft,
        ]);
        PurchaseItem::factory()->create([
            'shop_id' => $this->shop->id,
            'purchase_id' => $purchase->id,
            'product_id' => $product->id,
        ]);
        $before = AuditLog::count();
        app(PostPurchase::class)->execute($purchase, $this->user->id);
        $this->assertGreaterThanOrEqual($before + 1, AuditLog::count());
    }

    public function test_cash_session_open_close_write_audit(): void
    {
        $before = AuditLog::count();
        $session = app(OpenCashSession::class)->handle($this->user->id, ['opening_cash' => '10.00']);
        $this->assertSame($before + 1, AuditLog::count());

        $before2 = AuditLog::count();
        app(CloseCashSession::class)->handle($session, $this->user->id, ['closing_cash' => '10.00']);
        $this->assertSame($before2 + 1, AuditLog::count());
    }

    public function test_expense_create_update_delete_write_audit(): void
    {
        $category = ExpenseCategory::factory()->create(['shop_id' => $this->shop->id]);
        $before = AuditLog::count();
        $expense = app(CreateExpense::class)->handle($this->user->id, [
            'category_id' => $category->id,
            'amount' => '5.00',
            'expense_date' => now()->toDateString(),
        ]);
        $this->assertSame($before + 1, AuditLog::count());

        $before2 = AuditLog::count();
        app(UpdateExpense::class)->handle($expense, ['amount' => '6.00']);
        $this->assertSame($before2 + 1, AuditLog::count());

        $before3 = AuditLog::count();
        app(DeleteExpense::class)->handle($expense);
        $this->assertSame($before3 + 1, AuditLog::count());
    }

    public function test_product_update_writes_audit(): void
    {
        $product = Product::factory()->create(['shop_id' => $this->shop->id]);
        $before = AuditLog::count();
        app(UpdateProduct::class)->execute($product, [
            'name' => 'Updated',
            'sku' => $product->sku,
            'unit_id' => $product->unit_id,
            'cost_price' => '12.00',
            'selling_price' => '15.00',
            'track_stock' => true,
        ]);
        $this->assertSame($before + 1, AuditLog::count());
    }

    public function test_add_staff_member_writes_audit(): void
    {
        Notification::fake();
        $role = $this->shop->roles()->where('name', 'Cashier')->firstOrFail();
        $before = AuditLog::count();

        $membership = app(AddStaffMember::class)->execute([
            'name' => 'Audited Staff',
            'email' => 'audited-staff@example.test',
            'phone' => '9841234567',
            'role_id' => (int) $role->id,
            'password' => 'Temporary123',
        ], (int) $this->user->id);

        $this->assertSame($before + 1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'event' => 'staff_added',
            'auditable_id' => $membership->id,
        ]);
        $this->assertArrayNotHasKey('password', AuditLog::query()->where('event', 'staff_added')->latest('id')->firstOrFail()->new_values);
    }

    public function test_change_staff_role_writes_audit(): void
    {
        $staffUser = User::factory()->create();
        $cashier = $this->shop->roles()->where('name', 'Cashier')->firstOrFail();
        $accountant = $this->shop->roles()->where('name', 'Accountant')->firstOrFail();
        $membership = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $staffUser->id,
            'role_id' => $cashier->id,
            'status' => 'active',
        ]);
        $before = AuditLog::count();

        app(ChangeStaffRole::class)->execute($membership, (int) $accountant->id, (int) $this->user->id);

        $this->assertSame($before + 1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'event' => 'staff_role_changed',
            'auditable_id' => $membership->id,
        ]);
    }

    public function test_remove_staff_member_writes_audit(): void
    {
        $staffUser = User::factory()->create();
        $role = Role::query()->where('name', 'Cashier')->firstOrFail();
        $membership = ShopUser::create([
            'shop_id' => $this->shop->id,
            'user_id' => $staffUser->id,
            'role_id' => $role->id,
            'status' => 'active',
        ]);
        $before = AuditLog::count();

        app(RemoveStaffMember::class)->execute($membership, (int) $this->user->id);

        $this->assertSame($before + 1, AuditLog::count());
        $this->assertDatabaseHas('audit_logs', [
            'shop_id' => $this->shop->id,
            'user_id' => $this->user->id,
            'event' => 'staff_removed',
            'auditable_id' => $membership->id,
        ]);
        $this->assertDatabaseMissing('shop_user', ['id' => $membership->id]);
        $this->assertDatabaseHas('users', ['id' => $staffUser->id]);
    }
}
