<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // sales
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

        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_prevent_posted_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sales_prevent_posted_delete
            BEFORE DELETE ON sales
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted sales cannot be deleted.';
                END IF;
            END
        SQL);

        // sale_items
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sale_items_prevent_posted_update
            BEFORE UPDATE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) IN ('posted', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Sale items of posted sales cannot be modified.';
                END IF;
            END
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sale_items_prevent_posted_delete
            BEFORE DELETE ON sale_items
            FOR EACH ROW
            BEGIN
                IF (SELECT status FROM sales WHERE id = OLD.sale_id) IN ('posted', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Sale items of posted sales cannot be deleted.';
                END IF;
            END
        SQL);

        // stock_adjustments
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_restrict_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_stock_adjustments_restrict_update
            BEFORE UPDATE ON stock_adjustments
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted stock adjustments are immutable and cannot be modified.';
                END IF;
            END
        SQL);

        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_prevent_delete');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_stock_adjustments_prevent_delete
            BEFORE DELETE ON stock_adjustments
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('posted', 'cancelled') THEN
                    SIGNAL SQLSTATE '45000'
                    SET MESSAGE_TEXT = 'Posted stock adjustments cannot be deleted.';
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sales_prevent_posted_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_items_prevent_posted_delete');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_restrict_update');
        DB::unprepared('DROP TRIGGER IF EXISTS trg_stock_adjustments_prevent_delete');
    }
};
