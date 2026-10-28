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
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('product_id');
            $table->string('sku', 100);
            $table->string('barcode', 100)->nullable();
            $table->json('attributes')->nullable();
            $table->decimal('cost_price', 14, 2)->nullable();
            $table->decimal('selling_price', 14, 2)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->tenantCompositeIndex();
            $table->compositeTenantForeign('product_id', 'products');
            $table->unique(['shop_id', 'sku']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_variants');
    }
};
