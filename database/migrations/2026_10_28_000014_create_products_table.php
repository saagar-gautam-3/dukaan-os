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
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('categories')->nullOnDelete();
            $table->foreignId('unit_id')->constrained('units');
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->string('name');
            $table->string('name_ne')->nullable();
            $table->decimal('cost_price', 14, 2)->default(0.00);
            $table->decimal('selling_price', 14, 2)->default(0.00);
            $table->boolean('track_stock')->default(true);
            $table->decimal('low_stock_threshold', 14, 3)->default(5.000);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->tenantCompositeIndex();
            $table->unique(['shop_id', 'sku']);
            $table->index(['shop_id', 'barcode']);
            $table->index(['shop_id', 'category_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
