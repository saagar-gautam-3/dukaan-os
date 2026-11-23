<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Enums\LedgerEntryType;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Support\NepaliNumber;
use App\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Suppliers\Concerns\CreatesSupplierFixture;
use Tests\TestCase;

final class SupplierDuesTest extends TestCase
{
    use CreatesSupplierFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCreatesSupplierFixture();
        parent::tearDown();
    }

    public function test_payables_page_orders_by_balance_and_shows_totals_and_dates(): void
    {
        [$shop, , $users] = $this->createShopWithRoles(['Shop Owner', 'Cashier']);
        $this->actingAsShopMember($users['Shop Owner'], $shop);

        $supplierA = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Supplier Alpha']);
        $this->addPurchaseDebit($supplierA, '150.00', '2026-11-01 10:00:00');
        $this->post(route('suppliers.payments.store', $supplierA), [
            'amount' => '50.00',
            'payment_method' => 'bank',
            'payment_date' => '2026-11-10',
            'idempotency_key' => 'dues-a',
        ])->assertRedirect(route('suppliers.dues'));

        $supplierB = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Supplier Beta']);
        $this->addPurchaseDebit($supplierB, '250.00', '2026-10-20 09:00:00');

        $supplierPaid = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Supplier Paid']);
        $this->addPurchaseDebit($supplierPaid, '100.00', '2026-10-01 09:00:00');
        $this->post(route('suppliers.payments.store', $supplierPaid), [
            'amount' => '100.00',
            'payment_method' => 'bank',
            'payment_date' => '2026-11-15',
            'idempotency_key' => 'dues-paid',
        ])->assertRedirect(route('suppliers.dues'));

        $response = $this->get(route('suppliers.dues'))->assertOk();

        $html = (string) $response->getContent();
        $this->assertLessThan(strpos($html, 'Supplier Alpha'), strpos($html, 'Supplier Beta'));

        $this->assertStringNotContainsString('Supplier Paid', $html);
        $this->assertStringContainsString('2026-10-20', $html);
        $this->assertStringContainsString('2026-11-10', $html);
        $this->assertStringContainsString(NepaliNumber::formatNpr('350.00'), $html);
        $this->assertStringContainsString(NepaliNumber::formatNpr('250.00'), $html);
        $this->assertStringContainsString(NepaliNumber::formatNpr('100.00'), $html);
        $this->assertStringContainsString(__('suppliers.total_payable'), $html);
    }

    public function test_empty_state_is_shown_when_there_are_no_payables(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '10.00');
        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '10.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'dues-empty',
        ])->assertRedirect(route('suppliers.dues'));

        $this->get(route('suppliers.dues'))
            ->assertOk()
            ->assertSee(__('suppliers.no_dues'));
    }

    public function test_another_shops_suppliers_are_never_listed(): void
    {
        [$shopA, , $users] = $this->createShopWithRoles();
        $shopB = Shop::factory()->create();
        $foreignSupplier = TenantContext::runAs($shopB, fn () => Supplier::factory()->create([
            'shop_id' => $shopB->id,
            'name' => 'Foreign Supplier',
        ]));
        TenantContext::runAs($shopB, fn () => $this->addPurchaseDebit($foreignSupplier, '500.00'));

        $this->actingAsShopMember($users['Shop Owner'], $shopA);
        $this->get(route('suppliers.dues'))
            ->assertOk()
            ->assertDontSee('Foreign Supplier');
    }

    public function test_payables_page_has_constant_query_count(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $this->actingAsShopMember($users['Shop Owner'], $shop);

        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '10.00');

        $countQueries = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->get(route('suppliers.dues'))->assertOk();
            $count = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $count;
        };

        $baseline = $countQueries();

        for ($i = 0; $i < 8; $i++) {
            $extra = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Extra '.$i]);
            $this->addPurchaseDebit($extra, (string) (10 + $i).'.00');
        }

        $this->assertSame($baseline, $countQueries());
    }

    public function test_payables_page_requires_suppliers_view_permission(): void
    {
        [$shop, , $users] = $this->createShopWithRoles(['Cashier']);
        $this->actingAsShopMember($users['Cashier'], $shop);

        $this->get(route('suppliers.dues'))->assertForbidden();
    }

    public function test_dues_page_hides_payment_link_from_roles_without_suppliers_pay(): void
    {
        [$shop, , $users] = $this->createShopWithRoles(['Inventory Staff']);
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'No Permission Supplier']);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($users['Inventory Staff'], $shop);
        $response = $this->get(route('suppliers.index'))->assertOk();

        $response->assertDontSee(route('suppliers.payments.create', $supplier));
    }

    public function test_payment_credit_entry_can_drive_last_payment_date_without_stored_payment_table(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Dated Supplier']);
        $this->addPurchaseDebit($supplier, '40.00', '2026-10-05 08:00:00');

        SupplierLedgerEntry::factory()->create([
            'shop_id' => $shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => LedgerEntryType::SupplierPaymentCredit->value,
            'credit' => '10.00',
            'created_at' => '2026-11-12 12:00:00',
        ]);

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->get(route('suppliers.dues'))
            ->assertOk()
            ->assertSee('2026-11-12')
            ->assertSee('2026-10-05');
    }
}
