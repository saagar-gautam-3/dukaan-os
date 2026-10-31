<?php

declare(strict_types=1);

namespace Tests\Feature\Integrity;

use App\Enums\AdjustmentStatus;
use App\Enums\CashSessionStatus;
use App\Enums\PaymentStatus;
use App\Enums\ReturnStatus;
use App\Enums\SaleStatus;
use App\Exceptions\ImmutableRecordException;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CustomerLedgerEntry;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SaleReturn;
use App\Models\SaleReturnItem;
use App\Models\Shop;
use App\Models\StockAdjustment;
use App\Models\StockAdjustmentItem;
use App\Models\StockMovement;
use App\Models\SupplierLedgerEntry;
use App\Tenancy\TenantContext;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Verifies the dual-layer immutability enforcement (Eloquent model guards +
 * MySQL BEFORE UPDATE/DELETE triggers) for every table class (A, B, C).
 *
 * Model-layer tests: catch ImmutableRecordException thrown by booted() hooks.
 * DB-layer tests:    raw DB::table() DML must throw a PDOException / QueryException.
 */
class ImmutabilityTest extends TestCase
{
    use RefreshDatabase;

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: customer_ledger_entries
    // ──────────────────────────────────────────────────────────────────────────

    public function test_customer_ledger_entry_model_update_throws_immutable_exception(): void
    {
        $entry = CustomerLedgerEntry::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $entry->update(['notes' => 'tampered']);
    }

    public function test_customer_ledger_entry_model_delete_throws_immutable_exception(): void
    {
        $entry = CustomerLedgerEntry::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $entry->delete();
    }

    public function test_customer_ledger_entries_db_update_blocked_by_trigger(): void
    {
        $entry = CustomerLedgerEntry::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('customer_ledger_entries')->where('id', $entry->id)->update(['notes' => 'tampered']);
    }

    public function test_customer_ledger_entries_db_delete_blocked_by_trigger(): void
    {
        $entry = CustomerLedgerEntry::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('customer_ledger_entries')->where('id', $entry->id)->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: supplier_ledger_entries
    // ──────────────────────────────────────────────────────────────────────────

    public function test_supplier_ledger_entry_model_update_throws_immutable_exception(): void
    {
        $entry = SupplierLedgerEntry::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $entry->update(['notes' => 'tampered']);
    }

    public function test_supplier_ledger_entries_db_update_blocked_by_trigger(): void
    {
        $entry = SupplierLedgerEntry::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('supplier_ledger_entries')->where('id', $entry->id)->update(['notes' => 'tampered']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: stock_movements
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stock_movement_model_update_throws_immutable_exception(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $movement->update(['notes' => 'tampered']);
    }

    public function test_stock_movement_model_delete_throws_immutable_exception(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $movement->delete();
    }

    public function test_stock_movements_db_update_blocked_by_trigger(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('stock_movements')->where('id', $movement->id)->update(['notes' => 'tampered']);
    }

    public function test_stock_movements_db_delete_blocked_by_trigger(): void
    {
        $movement = StockMovement::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('stock_movements')->where('id', $movement->id)->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: audit_logs
    // ──────────────────────────────────────────────────────────────────────────

    public function test_audit_log_model_update_throws_immutable_exception(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $log->update(['event' => 'tampered']);
    }

    public function test_audit_logs_db_update_blocked_by_trigger(): void
    {
        $log = AuditLog::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('audit_logs')->where('id', $log->id)->update(['event' => 'tampered']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: cash_movements
    // ──────────────────────────────────────────────────────────────────────────

    public function test_cash_movement_model_update_throws_immutable_exception(): void
    {
        $movement = CashMovement::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $movement->update(['notes' => 'tampered']);
    }

    public function test_cash_movements_db_update_blocked_by_trigger(): void
    {
        $movement = CashMovement::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('cash_movements')->where('id', $movement->id)->update(['notes' => 'tampered']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: payment_allocations
    // ──────────────────────────────────────────────────────────────────────────

    public function test_payment_allocation_model_update_throws_immutable_exception(): void
    {
        $alloc = PaymentAllocation::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $alloc->update(['amount' => '999.00']);
    }

    public function test_payment_allocations_db_update_blocked_by_trigger(): void
    {
        $alloc = PaymentAllocation::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('payment_allocations')->where('id', $alloc->id)->update(['amount' => '999.00']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: sale_return_items
    // ──────────────────────────────────────────────────────────────────────────

    public function test_sale_return_item_model_update_throws_immutable_exception(): void
    {
        $item = SaleReturnItem::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $item->update(['quantity' => '999.000']);
    }

    public function test_sale_return_items_db_delete_blocked_by_trigger(): void
    {
        $item = SaleReturnItem::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('sale_return_items')->where('id', $item->id)->delete();
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class A — append-only: stock_adjustment_items
    // ──────────────────────────────────────────────────────────────────────────

    public function test_stock_adjustment_item_model_update_throws_immutable_exception(): void
    {
        $item = StockAdjustmentItem::factory()->create();

        $this->expectException(ImmutableRecordException::class);
        $item->update(['quantity' => '999.000']);
    }

    public function test_stock_adjustment_items_db_update_blocked_by_trigger(): void
    {
        $item = StockAdjustmentItem::factory()->create();

        $this->expectException(QueryException::class);
        DB::table('stock_adjustment_items')->where('id', $item->id)->update(['quantity' => '999.000']);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class C — posted sales: frozen core fields, allow paid_total update
    // ──────────────────────────────────────────────────────────────────────────

    public function test_posted_sale_core_field_update_throws_immutable_exception(): void
    {
        $sale = Sale::factory()->create(['status' => SaleStatus::Posted]);

        $this->expectException(ImmutableRecordException::class);
        $sale->update(['grand_total' => '999.00']);
    }

    public function test_posted_sale_delete_throws_immutable_exception(): void
    {
        $sale = Sale::factory()->create(['status' => SaleStatus::Posted]);

        $this->expectException(ImmutableRecordException::class);
        $sale->delete();
    }

    public function test_sales_db_update_frozen_field_blocked_by_trigger(): void
    {
        $sale = Sale::factory()->create(['status' => SaleStatus::Posted]);

        $this->expectException(QueryException::class);
        DB::table('sales')->where('id', $sale->id)->update(['grand_total' => '999.00']);
    }

    public function test_posted_sale_paid_total_update_is_allowed(): void
    {
        // paid_total is a running-balance cache column — it MUST be mutable
        $sale = Sale::factory()->create(['status' => SaleStatus::Posted, 'paid_total' => '0.00']);

        $sale->update(['paid_total' => '50.00']);

        $this->assertSame('50.00', (string) $sale->fresh()->paid_total);
    }

    public function test_draft_sale_can_be_updated(): void
    {
        $sale = Sale::factory()->create(['status' => SaleStatus::Draft]);

        $sale->update(['notes' => 'updated note']);

        $this->assertSame('updated note', $sale->fresh()->notes);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class B — sale_items: frozen when parent sale is posted
    // ──────────────────────────────────────────────────────────────────────────

    public function test_posted_sale_item_update_throws_immutable_exception(): void
    {
        $item = SaleItem::factory()->create();
        $sale = Sale::withoutGlobalScopes()->findOrFail($item->sale_id);
        TenantContext::runAs(Shop::find($sale->shop_id), function () use ($sale) {
            $sale->update(['status' => SaleStatus::Posted, 'posted_at' => now()]);
        });

        $this->expectException(ImmutableRecordException::class);
        TenantContext::runAs(Shop::find($item->shop_id), function () use ($item) {
            $item->fresh()->update(['quantity' => '99.000']);
        });
    }

    public function test_sale_items_db_update_blocked_by_trigger_when_sale_posted(): void
    {
        $item = SaleItem::factory()->create();
        $sale = Sale::withoutGlobalScopes()->findOrFail($item->sale_id);
        TenantContext::runAs(Shop::find($sale->shop_id), function () use ($sale) {
            $sale->update(['status' => SaleStatus::Posted, 'posted_at' => now()]);
        });

        $this->expectException(QueryException::class);
        DB::table('sale_items')->where('id', $item->id)->update(['quantity' => '99.000']);
    }

    public function test_draft_sale_item_can_be_updated(): void
    {
        $item = SaleItem::factory()->create();

        $item->update(['quantity' => '2.000']);

        $this->assertSame('2.000', (string) $item->fresh()->quantity);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class C — payments: frozen financial fields; status flow; no deletes
    // ──────────────────────────────────────────────────────────────────────────

    public function test_payment_model_amount_update_throws_immutable_exception(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $this->expectException(ImmutableRecordException::class);
        $payment->update(['amount' => '999.00']);
    }

    public function test_payment_model_delete_throws_immutable_exception(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $this->expectException(ImmutableRecordException::class);
        $payment->delete();
    }

    public function test_payments_db_update_frozen_field_blocked_by_trigger(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $this->expectException(QueryException::class);
        DB::table('payments')->where('id', $payment->id)->update(['amount' => '999.00']);
    }

    public function test_payments_db_delete_blocked_by_trigger(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $this->expectException(QueryException::class);
        DB::table('payments')->where('id', $payment->id)->delete();
    }

    public function test_payment_pending_to_verified_status_transition_is_allowed(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $payment->update(['status' => PaymentStatus::Verified]);

        $this->assertSame(PaymentStatus::Verified, $payment->fresh()->status);
    }

    public function test_payment_verified_status_is_terminal(): void
    {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Verified]);

        $this->expectException(ImmutableRecordException::class);
        $payment->update(['status' => PaymentStatus::Failed]);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class C — cash_sessions: closed → fully immutable
    // ──────────────────────────────────────────────────────────────────────────

    public function test_closed_cash_session_update_throws_immutable_exception(): void
    {
        $session = CashSession::factory()->create(['status' => CashSessionStatus::Closed]);

        $this->expectException(ImmutableRecordException::class);
        $session->update(['notes' => 'tampered']);
    }

    public function test_cash_sessions_db_update_blocked_by_trigger_when_closed(): void
    {
        $session = CashSession::factory()->create(['status' => CashSessionStatus::Closed]);

        $this->expectException(QueryException::class);
        DB::table('cash_sessions')->where('id', $session->id)->update(['notes' => 'tampered']);
    }

    public function test_open_cash_session_can_be_closed(): void
    {
        $session = CashSession::factory()->create(['status' => CashSessionStatus::Open]);

        $session->update(['status' => CashSessionStatus::Closed, 'closed_at' => now()]);

        $this->assertSame(CashSessionStatus::Closed, $session->fresh()->status);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class C — stock_adjustments: posted → frozen
    // ──────────────────────────────────────────────────────────────────────────

    public function test_posted_stock_adjustment_update_throws_immutable_exception(): void
    {
        $adjustment = StockAdjustment::factory()->create(['status' => AdjustmentStatus::Posted]);

        $this->expectException(ImmutableRecordException::class);
        $adjustment->update(['reason' => 'tampered']);
    }

    public function test_stock_adjustments_db_update_blocked_by_trigger_when_posted(): void
    {
        $adjustment = StockAdjustment::factory()->create(['status' => AdjustmentStatus::Posted]);

        $this->expectException(QueryException::class);
        DB::table('stock_adjustments')->where('id', $adjustment->id)->update(['reason' => 'tampered']);
    }

    public function test_draft_stock_adjustment_can_be_updated(): void
    {
        $adjustment = StockAdjustment::factory()->create(['status' => AdjustmentStatus::Draft]);

        $adjustment->update(['reason' => 'corrected reason']);

        $this->assertSame('corrected reason', $adjustment->fresh()->reason);
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Class B — sale_returns: completed/cancelled financial fields are frozen
    // ──────────────────────────────────────────────────────────────────────────

    public function test_completed_sale_return_update_throws_immutable_exception(): void
    {
        $saleReturn = SaleReturn::factory()->create(['status' => ReturnStatus::Completed]);

        $this->expectException(ImmutableRecordException::class);
        $saleReturn->update(['reason' => 'tampered']);
    }

    public function test_completed_sale_return_delete_throws_immutable_exception(): void
    {
        $saleReturn = SaleReturn::factory()->create(['status' => ReturnStatus::Completed]);

        $this->expectException(ImmutableRecordException::class);
        $saleReturn->delete();
    }

    public function test_completed_sale_returns_db_total_update_blocked_by_trigger(): void
    {
        $saleReturn = SaleReturn::factory()->create([
            'status' => ReturnStatus::Completed,
            'total' => '100.00',
        ]);

        $this->expectException(QueryException::class);
        DB::update('UPDATE sale_returns SET total = ? WHERE id = ?', ['999.00', $saleReturn->id]);
    }

    public function test_completed_sale_returns_db_sale_and_shop_update_blocked_by_trigger(): void
    {
        $saleReturn = SaleReturn::factory()->create(['status' => ReturnStatus::Completed]);
        $otherShop = Shop::factory()->create();
        $otherSale = TenantContext::runAs($otherShop, fn () => Sale::factory()->create([
            'shop_id' => $otherShop->id,
        ]));

        $this->expectException(QueryException::class);
        DB::update(
            'UPDATE sale_returns SET sale_id = ?, shop_id = ? WHERE id = ?',
            [$otherSale->id, $otherShop->id, $saleReturn->id]
        );
    }

    public function test_completed_sale_returns_db_delete_blocked_by_trigger(): void
    {
        $saleReturn = SaleReturn::factory()->create(['status' => ReturnStatus::Completed]);

        $this->expectException(QueryException::class);
        DB::delete('DELETE FROM sale_returns WHERE id = ?', [$saleReturn->id]);
    }

    public function test_pending_sale_return_financial_fields_can_be_updated(): void
    {
        $saleReturn = SaleReturn::factory()->create([
            'status' => ReturnStatus::Pending,
            'total' => '100.00',
        ]);
        $otherShop = Shop::factory()->create();
        $otherSale = TenantContext::runAs($otherShop, fn () => Sale::factory()->create([
            'shop_id' => $otherShop->id,
        ]));

        DB::update(
            'UPDATE sale_returns SET total = ?, sale_id = ?, shop_id = ? WHERE id = ?',
            ['125.00', $otherSale->id, $otherShop->id, $saleReturn->id]
        );

        $this->assertDatabaseHas('sale_returns', [
            'id' => $saleReturn->id,
            'total' => '125.00',
            'sale_id' => $otherSale->id,
            'shop_id' => $otherShop->id,
        ]);
    }
}
