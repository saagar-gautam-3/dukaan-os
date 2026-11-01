<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_returns_restrict_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sale_returns_restrict_update
            BEFORE UPDATE ON sale_returns
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('completed', 'cancelled') THEN
                    IF NOT (NEW.total <=> OLD.total)
                       OR (NEW.sale_id <> OLD.sale_id)
                       OR (NEW.shop_id <> OLD.shop_id)
                       OR (NEW.status <> OLD.status)
                       OR (NEW.return_no <> OLD.return_no) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Completed sale return totals, sale, shop, and return_no are frozen.';
                    END IF;
                END IF;
            END
        SQL);
    }

    public function down(): void
    {
        DB::unprepared('DROP TRIGGER IF EXISTS trg_sale_returns_restrict_update');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER trg_sale_returns_restrict_update
            BEFORE UPDATE ON sale_returns
            FOR EACH ROW
            BEGIN
                IF OLD.status IN ('completed', 'cancelled') THEN
                    IF NOT (NEW.total <=> OLD.total)
                       OR (NEW.sale_id <> OLD.sale_id)
                       OR (NEW.shop_id <> OLD.shop_id)
                       OR (NEW.status <> OLD.status) THEN
                        SIGNAL SQLSTATE '45000'
                        SET MESSAGE_TEXT = 'Completed sale return totals, sale, and shop are frozen.';
                    END IF;
                END IF;
            END
        SQL);
    }
};
