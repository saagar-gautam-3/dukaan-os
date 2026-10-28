<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Optional warehouse — simple FK; tenant isolation enforced at app level
            $table->foreignId('warehouse_id')->nullable()->constrained('warehouses')->nullOnDelete();

            // Optional customer — simple FK; tenant isolation enforced at app level
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('invoice_no')->nullable(); // set on post
            $table->string('status', 20)->default('draft'); // draft|posted|cancelled

            // Monetary totals — DECIMAL(14,2), bcmath only
            $table->decimal('subtotal', 14, 2)->default('0.00');
            $table->decimal('discount_total', 14, 2)->default('0.00');
            $table->decimal('tax_total', 14, 2)->default('0.00');
            $table->decimal('grand_total', 14, 2)->default('0.00');
            $table->decimal('paid_total', 14, 2)->default('0.00'); // cached sum of verified payments

            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->foreignId('posted_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            // Business constraints
            $table->unique(['shop_id', 'invoice_no']);
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'created_at']);
            $table->index(['shop_id', 'customer_id']);

            // Composite unique index so child tables can use composite FK (shop_id, id)
            $table->unique(['shop_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
