<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Enums\LedgerEntryType;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Suppliers\Concerns\CreatesSupplierFixture;
use Tests\TestCase;

final class SupplierLedgerReconcileTest extends TestCase
{
    use CreatesSupplierFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCreatesSupplierFixture();
        parent::tearDown();
    }

    public function test_reconcile_reports_a_consistent_supplier_ledger(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '40.00',
            'payment_method' => 'bank',
            'idempotency_key' => 'reconcile-ok',
        ])->assertRedirect(route('suppliers.dues'));

        $this->artisan('ledger:reconcile', ['--shop' => $shop->id])
            ->assertExitCode(0)
            ->expectsOutputToContain('Supplier ledger balances are consistent');
    }

    public function test_reconcile_flags_negative_supplier_balance_as_a_drift(): void
    {
        [$shop] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Advance Supplier']);

        SupplierLedgerEntry::factory()->create([
            'shop_id' => $shop->id,
            'supplier_id' => $supplier->id,
            'entry_type' => LedgerEntryType::SupplierPaymentCredit->value,
            'debit' => '0.00',
            'credit' => '20.00',
        ]);

        $this->artisan('ledger:reconcile', ['--shop' => $shop->id])
            ->assertExitCode(1)
            ->expectsOutputToContain('supplier ledger issue(s)')
            ->expectsOutputToContain('negative_balance');
    }
}
