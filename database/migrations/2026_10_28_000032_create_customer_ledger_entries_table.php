<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to customers (tenant-safe)
            $table->unsignedBigInteger('customer_id');
            $table->foreign(['shop_id', 'customer_id'])
                ->references(['shop_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();

            $table->string('entry_type', 50); // e.g. 'sale_credit', 'payment_debit', 'return_credit', 'manual'
            $table->decimal('debit', 14, 2)->default('0.00');   // customer owes more
            $table->decimal('credit', 14, 2)->default('0.00');  // customer paid / credit applied
            $table->string('reference_type', 100)->nullable();  // 'sale', 'payment', 'sale_return', etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Composite unique index for composite FK support by child tables
            $table->unique(['shop_id', 'id']);

            // Indexes for fast queries
            $table->index(['shop_id', 'customer_id', 'created_at'], 'cust_ledger_cust_idx');
            $table->index(['shop_id', 'reference_type', 'reference_id'], 'cust_ledger_ref_idx');
            $table->index(['shop_id', 'created_at'], 'cust_ledger_created_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customer_ledger_entries');
    }
};
