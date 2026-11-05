<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Recreate the sales UPDATE trigger, preserving every existing rule and
        // adding a constraint on the status column: only the transitions the
        // application actually performs are allowed.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_restrict_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sales_restrict_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'cancelled') THEN
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

                -- Constrain status transitions to those the application performs:
                --   draft -> posted   (PostSale)
                --   draft -> cancelled (CancelSale)
                -- Any other change (including posted -> draft, cancelled -> *,
                -- posted -> cancelled, or any -> unknown value) is rejected.
                IF NOT (NEW.status <=> OLD.status) THEN
                    IF NOT (
                        (OLD.status = 'draft' AND NEW.status = 'posted')
                        OR (OLD.status = 'draft' AND NEW.status = 'cancelled')
                    ) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Sale status transition not allowed.';
                    END IF;
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        // Restore the previous trigger body exactly.
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_restrict_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sales_restrict_update
            BEFORE UPDATE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'cancelled') THEN
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
        SQL);
    }
};
