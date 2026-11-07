<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("UPDATE shops SET settings = JSON_SET(COALESCE(settings, '{}'), '$.sku_auto_generate', true) WHERE JSON_EXTRACT(settings, '$.sku_auto_generate') IS NULL");
    }

    public function down(): void
    {
        // Do not touch products.sku.
    }
};
