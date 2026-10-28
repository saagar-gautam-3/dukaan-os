<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('warehouse_id');
            $table->foreignId('product_id');
            $table->decimal('quantity', 14, 3)->default(0.000);
            $table->timestamps();

            $table->tenantCompositeIndex();
            $table->compositeTenantForeign('warehouse_id', 'warehouses');
            $table->compositeTenantForeign('product_id', 'products');
            $table->unique(['shop_id', 'warehouse_id', 'product_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_balances');
    }
};
