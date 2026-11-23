<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Actions\Cash\OpenCashSession;
use App\Enums\CashMovementType;
use App\Enums\LedgerEntryType;
use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\Shop;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\Suppliers\Concerns\CreatesSupplierFixture;
use Tests\TestCase;

final class SupplierPaymentTest extends TestCase
{
    use CreatesSupplierFixture;
    use RefreshDatabase;

    protected function tearDown(): void
    {
        $this->tearDownCreatesSupplierFixture();
        parent::tearDown();
    }

    public function test_partial_then_full_payment_keeps_the_ledger_invariant(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $user = $users['Shop Owner'];
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($user, $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '33.33',
            'payment_method' => 'cash',
            'idempotency_key' => 'partial',
        ])->assertRedirect(route('suppliers.dues'));

        $this->assertSame('66.67', $supplier->fresh()->ledgerBalance());

        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '66.67',
            'payment_method' => 'bank',
            'idempotency_key' => 'full',
        ])->assertRedirect(route('suppliers.dues'));

        $this->assertSame('0.00', $supplier->fresh()->ledgerBalance());
        $this->assertSame(3, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
        $this->assertSame(
            '0.00',
            Money::subtract(
                (string) SupplierLedgerEntry::where('supplier_id', $supplier->id)->sum('debit'),
                (string) SupplierLedgerEntry::where('supplier_id', $supplier->id)->sum('credit'),
                2,
            ),
        );
    }

    public function test_small_and_large_two_decimal_amounts_are_accepted(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '9999999999.99');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '0.01',
            'payment_method' => 'qr',
            'idempotency_key' => 'small',
        ])->assertRedirect(route('suppliers.dues'));

        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '9999999999.98',
            'payment_method' => 'bank',
            'idempotency_key' => 'large',
        ])->assertRedirect(route('suppliers.dues'));

        $this->assertSame('0.00', $supplier->fresh()->ledgerBalance());
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function invalidAmounts(): array
    {
        return [
            'zero' => ['0'],
            'zero with decimals' => ['0.00'],
            'negative' => ['-5.00'],
            'non numeric' => ['abc'],
            'three decimals' => ['10.001'],
        ];
    }

    #[DataProvider('invalidAmounts')]
    public function test_invalid_amounts_are_rejected(string $amount): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $response = $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => $amount,
            'payment_method' => 'cash',
        ]);

        $response->assertSessionHasErrors(['amount']);
        $this->assertSame(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
    }

    public function test_overpayment_is_rejected_with_a_friendly_localized_message(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '50.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '60.00',
            'payment_method' => 'cash',
        ])
            ->assertSessionHas('error', __('errors.supplier_payment_exceeds_balance'));

        $this->assertSame('50.00', $supplier->fresh()->ledgerBalance());
    }

    public function test_future_payment_date_is_rejected(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $futureDate = now($shop->timezone)->addDay()->toDateString();

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '10.00',
            'payment_method' => 'cash',
            'payment_date' => $futureDate,
        ])->assertSessionHasErrors(['payment_date']);

        $this->assertSame(1, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
    }

    public function test_forged_tenant_and_balance_fields_are_ignored(): void
    {
        [$shopA, , $users] = $this->createShopWithRoles();
        $supplierA = Supplier::factory()->create(['shop_id' => $shopA->id]);
        $this->addPurchaseDebit($supplierA, '25.00');

        $shopB = Shop::factory()->create();
        $supplierB = TenantContext::runAs($shopB, fn () => Supplier::factory()->create(['shop_id' => $shopB->id]));

        $this->actingAsShopMember($users['Shop Owner'], $shopA);
        $this->post(route('suppliers.payments.store', $supplierA), [
            'amount' => '10.00',
            'payment_method' => 'cash',
            'shop_id' => $shopB->id,
            'supplier_id' => $supplierB->id,
            'balance' => '999999.99',
            'idempotency_key' => 'forged',
        ])->assertRedirect(route('suppliers.dues'));

        $entry = SupplierLedgerEntry::where('reference_type', 'supplier_payment')->sole();
        $this->assertSame($shopA->id, $entry->shop_id);
        $this->assertSame($supplierA->id, $entry->supplier_id);
        $this->assertSame('15.00', $supplierA->fresh()->ledgerBalance());
        $this->assertSame(0, SupplierLedgerEntry::where('supplier_id', $supplierB->id)->count());
    }

    public function test_double_submit_with_the_same_key_records_one_payment(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $payload = [
            'amount' => '40.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'double-click',
        ];

        $this->post(route('suppliers.payments.store', $supplier), $payload)->assertRedirect(route('suppliers.dues'));
        $this->post(route('suppliers.payments.store', $supplier), $payload)->assertRedirect(route('suppliers.dues'));

        $this->assertSame(2, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
        $this->assertSame('60.00', $supplier->fresh()->ledgerBalance());
    }

    public function test_a_second_payment_cannot_overpay_after_the_first_commits(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '60.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'first',
        ])->assertRedirect(route('suppliers.dues'));

        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '60.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'second',
        ])->assertSessionHas('error', __('errors.supplier_payment_exceeds_balance'));

        $this->assertSame('40.00', $supplier->fresh()->ledgerBalance());
        $this->assertSame(2, SupplierLedgerEntry::where('supplier_id', $supplier->id)->count());
    }

    public function test_cash_payment_writes_cash_out_movement_but_bank_and_qr_do_not(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $user = $users['Shop Owner'];
        app(OpenCashSession::class)->handle($user->id, ['opening_cash' => '1000.00']);

        $this->actingAsShopMember($user, $shop);

        foreach (['cash' => true, 'bank' => false, 'qr' => false] as $method => $expectsMovement) {
            $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
            $this->addPurchaseDebit($supplier, '50.00');

            $this->post(route('suppliers.payments.store', $supplier), [
                'amount' => '50.00',
                'payment_method' => $method,
                'idempotency_key' => 'method-'.$method,
            ])->assertRedirect(route('suppliers.dues'));

            $entry = SupplierLedgerEntry::where('supplier_id', $supplier->id)
                ->where('entry_type', LedgerEntryType::SupplierPaymentCredit->value)
                ->sole();

            $movement = CashMovement::where('reference_type', 'supplier_payment')
                ->where('reference_id', $entry->id)
                ->first();

            if ($expectsMovement) {
                $this->assertNotNull($movement);
                $this->assertSame(CashMovementType::ExpenseCash->value, $movement->type->value);
                $this->assertSame('50.00', $movement->amount);
            } else {
                $this->assertNull($movement);
            }
        }
    }

    public function test_cash_payment_without_an_open_session_does_not_fail(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '20.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '20.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'no-session',
        ])->assertRedirect(route('suppliers.dues'));

        $this->assertSame(0, CashMovement::where('reference_type', 'supplier_payment')->count());
        $this->assertSame('0.00', $supplier->fresh()->ledgerBalance());
    }

    public function test_ledger_entry_is_immutable(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '30.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '30.00',
            'payment_method' => 'bank',
            'idempotency_key' => 'immutable',
        ])->assertRedirect(route('suppliers.dues'));

        $entry = SupplierLedgerEntry::where('entry_type', LedgerEntryType::SupplierPaymentCredit->value)->sole();

        $this->assertThrows(
            fn () => $entry->update(['credit' => '1.00']),
            ImmutableRecordException::class,
        );
        $this->assertThrows(
            fn () => $entry->delete(),
            ImmutableRecordException::class,
        );
        $this->assertThrows(
            fn () => DB::table('supplier_ledger_entries')->where('id', $entry->id)->update(['credit' => '1.00']),
            QueryException::class,
        );
    }

    public function test_audit_row_records_payment_without_extra_pii(): void
    {
        [$shop, , $users] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id, 'name' => 'Unique Supplier Name']);
        $this->addPurchaseDebit($supplier, '10.00');

        $this->actingAsShopMember($users['Shop Owner'], $shop);
        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '10.00',
            'payment_method' => 'bank',
            'reference_number' => 'REF-1',
            'idempotency_key' => 'audit',
        ])->assertRedirect(route('suppliers.dues'));

        $entry = SupplierLedgerEntry::where('entry_type', LedgerEntryType::SupplierPaymentCredit->value)->sole();

        $audit = AuditLog::query()
            ->where('auditable_type', $entry->getMorphClass())
            ->where('auditable_id', $entry->id)
            ->where('event', 'recorded')
            ->firstOrFail();

        $this->assertSame($shop->id, $audit->shop_id);
        $this->assertSame('10.00', $audit->new_values['amount']);
        $this->assertSame('bank', $audit->new_values['method']);
        $this->assertStringNotContainsString('Unique Supplier Name', json_encode($audit->new_values));
    }

    /**
     * @return array<string, array{0: string, 1: bool}>
     */
    public static function paymentRoles(): array
    {
        return [
            'owner' => ['Shop Owner', true],
            'manager' => ['Manager', true],
            'accountant' => ['Accountant', true],
            'cashier' => ['Cashier', false],
            'inventory staff' => ['Inventory Staff', false],
        ];
    }

    #[DataProvider('paymentRoles')]
    public function test_payment_routes_enforce_role_permissions(string $roleName, bool $allowed): void
    {
        [$shop, , $users] = $this->createShopWithRoles([$roleName]);
        $user = $users[$roleName];
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);
        $this->addPurchaseDebit($supplier, '100.00');

        $this->actingAsShopMember($user, $shop);
        $this->get(route('suppliers.payments.create', $supplier))
            ->assertStatus($allowed ? 200 : 403);

        $this->post(route('suppliers.payments.store', $supplier), [
            'amount' => '10.00',
            'payment_method' => 'cash',
            'idempotency_key' => 'role-'.$roleName,
        ])->assertStatus($allowed ? 302 : 403);
    }

    public function test_anonymous_user_is_redirected_to_login(): void
    {
        [$shop] = $this->createShopWithRoles();
        $supplier = Supplier::factory()->create(['shop_id' => $shop->id]);

        $this->get(route('suppliers.payments.create', $supplier))->assertRedirect(route('login'));
    }

    public function test_another_shops_supplier_returns_not_found_on_payment_routes(): void
    {
        [$shopA, , $users] = $this->createShopWithRoles();
        $shopB = Shop::factory()->create();
        $supplierB = TenantContext::runAs($shopB, fn () => Supplier::factory()->create(['shop_id' => $shopB->id]));

        $this->actingAsShopMember($users['Shop Owner'], $shopA);
        $this->get(route('suppliers.payments.create', $supplierB))->assertNotFound();
        $this->post(route('suppliers.payments.store', $supplierB), [
            'amount' => '10.00',
            'payment_method' => 'cash',
        ])->assertNotFound();
    }
}
