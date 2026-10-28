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
        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('adjustment_id');
            $table->foreignId('product_id');
            $table->string('type', 10); // 'in' or 'out'
            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2)->default(0.00);
            $table->timestamps();

            $table->compositeTenantForeign('adjustment_id', 'stock_adjustments');
            $table->compositeTenantForeign('product_id', 'products');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
