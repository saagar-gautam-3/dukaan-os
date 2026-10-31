<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach ([
            'categories', 'idempotency_keys', 'invoice_sequences', 'payment_allocations',
            'product_variants', 'products', 'purchase_items', 'purchases', 'roles',
            'sale_items', 'sale_return_items', 'sale_returns', 'shop_user',
            'stock_adjustment_items', 'stock_adjustments', 'stock_balances',
            'subscriptions', 'suppliers', 'warehouses',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['shop_id', 'created_at']);
            });
        }

        foreach (['purchases', 'reminder_logs', 'shop_user', 'stock_adjustments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table): void {
                $table->index(['shop_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        foreach ([
            'categories', 'idempotency_keys', 'invoice_sequences', 'payment_allocations',
            'product_variants', 'products', 'purchase_items', 'purchases', 'roles',
            'sale_items', 'sale_return_items', 'sale_returns', 'shop_user',
            'stock_adjustment_items', 'stock_adjustments', 'stock_balances',
            'subscriptions', 'suppliers', 'warehouses',
        ] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_shop_id_created_at_index');
            });
        }

        foreach (['purchases', 'reminder_logs', 'shop_user', 'stock_adjustments'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName): void {
                $table->dropIndex($tableName.'_shop_id_status_index');
            });
        }
    }
};
