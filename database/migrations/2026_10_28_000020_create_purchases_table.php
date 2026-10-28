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
        Schema::create('purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('warehouse_id');
            $table->foreignId('supplier_id')->nullable();
            $table->string('purchase_no', 50);
            $table->string('status', 20)->default('draft');
            $table->decimal('subtotal', 14, 2)->default(0.00);
            $table->decimal('tax_total', 14, 2)->default(0.00);
            $table->decimal('discount_total', 14, 2)->default(0.00);
            $table->decimal('grand_total', 14, 2)->default(0.00);
            $table->text('notes')->nullable();
            $table->date('purchase_date');
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->tenantCompositeIndex();
            $table->compositeTenantForeign('warehouse_id', 'warehouses');
            $table->compositeTenantForeign('supplier_id', 'suppliers');
            $table->unique(['shop_id', 'purchase_no']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
