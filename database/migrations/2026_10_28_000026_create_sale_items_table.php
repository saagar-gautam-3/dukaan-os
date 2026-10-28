<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK: ensures sale_items cannot reference a sale from a different shop
            $table->foreignId('sale_id');
            $table->foreign(['shop_id', 'sale_id'])
                ->references(['shop_id', 'id'])
                ->on('sales')
                ->cascadeOnDelete();

            // Composite FK: ensures product belongs to same shop
            $table->foreignId('product_id');
            $table->foreign(['shop_id', 'product_id'])
                ->references(['shop_id', 'id'])
                ->on('products')
                ->restrictOnDelete();

            $table->foreignId('product_variant_id')->nullable()->constrained('product_variants')->nullOnDelete();

            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_price', 14, 2);   // price at time of sale
            $table->decimal('unit_cost', 14, 2);    // COGS snapshot at time of posting (never rewritten)
            $table->decimal('discount', 14, 2)->default('0.00');
            $table->decimal('tax', 14, 2)->default('0.00');
            $table->decimal('line_total', 14, 2);   // (unit_price - discount + tax) * quantity

            $table->timestamps();

            $table->index(['shop_id', 'sale_id']);
            $table->index(['shop_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
