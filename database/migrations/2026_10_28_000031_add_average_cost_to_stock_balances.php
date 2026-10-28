<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add average_cost to stock_balances for moving weighted average costing.
 * This is the unit_cost snapshot used when posting sales (COGS calculation).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('average_cost', 14, 2)->default('0.00')->after('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn('average_cost');
        });
    }
};
