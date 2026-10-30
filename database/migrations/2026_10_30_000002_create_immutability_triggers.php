<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // ── Class A: Strictly append-only tables (no UPDATE, no DELETE) ──

        $appendOnlyTables = [
            'customer_ledger_entries',
            'supplier_ledger_entries',
            'stock_movements',
            'audit_logs',
            'cash_movements',
            'payment_allocations',
            'sale_return_items',
            'stock_adjustment_items',
        ];

        foreach ($appendOnlyTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_prevent_update");
            DB::unprepared("
                CREATE TRIGGER trg_{$table}_prevent_update
                BEFORE UPDATE ON `{$table}`
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = '{$table} is strictly append-only: updates are forbidden.';
                END
            ");

            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_prevent_delete");
            DB::unprepared("
                CREATE TRIGGER trg_{$table}_prevent_delete
                BEFORE DELETE ON `{$table}`
                FOR EACH ROW
                BEGIN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = '{$table} is strictly append-only: deletes are forbidden.';
                END
            ");
        }

        // ── Class B: Immutable once the parent is posted ──

        // 1. purchases
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchases_prevent_posted_update');
        DB::unprepared("
            CREATE TRIGGER trg_purchases_prevent_posted_update
            BEFORE UPDATE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted purchases are immutable and cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchases_prevent_posted_delete');
        DB::unprepared("
            CREATE TRIGGER trg_purchases_prevent_posted_delete
            BEFORE DELETE ON purchases
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'posted' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted purchases cannot be deleted.';
                END IF;
            END
        ");

        // 2. purchase_items
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_items_prevent_posted_update');
        DB::unprepared("
            CREATE TRIGGER trg_purchase_items_prevent_posted_update
            BEFORE UPDATE ON purchase_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM purchases WHERE id = OLD.purchase_id) = 'posted' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Purchase items of posted purchases cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_items_prevent_posted_delete');
        DB::unprepared("
            CREATE TRIGGER trg_purchase_items_prevent_posted_delete
            BEFORE DELETE ON purchase_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM purchases WHERE id = OLD.purchase_id) = 'posted' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Purchase items of posted purchases cannot be deleted.';
                END IF;
            END
        ");

        // 3. sale_items
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_update');
        DB::unprepared("
            CREATE TRIGGER trg_sale_items_prevent_posted_update
            BEFORE UPDATE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) IN ('completed', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Sale items of posted sales cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_delete');
        DB::unprepared("
            CREATE TRIGGER trg_sale_items_prevent_posted_delete
            BEFORE DELETE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) IN ('completed', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Sale items of posted sales cannot be deleted.';
                END IF;
            END
        ");

        // ── Class C: Restricted-column updates ──

        // 1. sales
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_restrict_update');
        DB::unprepared("
            CREATE TRIGGER trg_sales_restrict_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('completed', 'cancelled') THEN
                    IF (NEW.invoice_no != OLD.invoice_no)
                       OR NOT (NEW.customer_id <=> OLD.customer_id)
                       OR (NEW.subtotal != OLD.subtotal)
                       OR (NEW.discount_total != OLD.discount_total)
                       OR (NEW.tax_total != OLD.tax_total)
                       OR (NEW.grand_total != OLD.grand_total)
                       OR (NEW.shop_id != OLD.shop_id) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Posted sales financial totals, invoice_no, and customer are frozen.';
                    END IF;
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_prevent_posted_delete');
        DB::unprepared("
            CREATE TRIGGER trg_sales_prevent_posted_delete
            BEFORE DELETE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('completed', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted sales cannot be deleted.';
                END IF;
            END
        ");

        // 2. payments
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_restrict_update');
        DB::unprepared("
            CREATE TRIGGER trg_payments_restrict_update
            BEFORE UPDATE ON payments
            FOR EACH ROW
            BEGIN
                IF (NEW.amount != OLD.amount)
                   OR (NEW.method != OLD.method)
                   OR (NEW.payment_no != OLD.payment_no)
                   OR (NEW.shop_id != OLD.shop_id) THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Payment amount, method, payment_no, and shop are frozen.';
                END IF;
                IF OLD.status IN ('verified', 'failed') AND NEW.status != OLD.status THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Terminal payment status cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_prevent_delete');
        DB::unprepared("
            CREATE TRIGGER trg_payments_prevent_delete
            BEFORE DELETE ON payments
            FOR EACH ROW
            BEGIN
                SIGNAL SQLSTATE '45000'
                SET MESSAGE_TEXT = 'Payments cannot be deleted.';
            END
        ");

        // 3. cash_sessions
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_sessions_restrict_update');
        DB::unprepared("
            CREATE TRIGGER trg_cash_sessions_restrict_update
            BEFORE UPDATE ON cash_sessions
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'closed' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Closed cash sessions are immutable and cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_sessions_prevent_delete');
        DB::unprepared("
            CREATE TRIGGER trg_cash_sessions_prevent_delete
            BEFORE DELETE ON cash_sessions
            FOR EACH ROW
            BEGIN
                IF OLD.status = 'closed' THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Closed cash sessions cannot be deleted.';
                END IF;
            END
        ");

        // 4. stock_adjustments
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_restrict_update');
        DB::unprepared("
            CREATE TRIGGER trg_stock_adjustments_restrict_update
            BEFORE UPDATE ON stock_adjustments
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('adjusted', 'posted') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted stock adjustments are immutable and cannot be modified.';
                END IF;
            END
        ");

        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_prevent_delete');
        DB::unprepared("
            CREATE TRIGGER trg_stock_adjustments_prevent_delete
            BEFORE DELETE ON stock_adjustments
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('adjusted', 'posted') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted stock adjustments cannot be deleted.';
                END IF;
            END
        ");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $appendOnlyTables = [
            'customer_ledger_entries',
            'supplier_ledger_entries',
            'stock_movements',
            'audit_logs',
            'cash_movements',
            'payment_allocations',
            'sale_return_items',
            'stock_adjustment_items',
        ];

        foreach ($appendOnlyTables as $table) {
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_prevent_update");
            DB::unprepared("DROP TRIGGER IF EXISTS trg_{$table}_prevent_delete");
        }

        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchases_prevent_posted_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchases_prevent_posted_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_items_prevent_posted_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_purchase_items_prevent_posted_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_delete');

        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_prevent_posted_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_payments_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_sessions_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_cash_sessions_prevent_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_prevent_delete');
    }
};

