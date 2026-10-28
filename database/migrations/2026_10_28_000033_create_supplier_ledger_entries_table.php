<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supplier_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to suppliers (tenant-safe)
            $table->unsignedBigInteger('supplier_id');
            $table->foreign(['shop_id', 'supplier_id'])
                ->references(['shop_id', 'id'])
                ->on('suppliers')
                ->cascadeOnDelete();

            $table->string('entry_type', 50); // e.g. 'purchase_debit', 'payment_credit'
            $table->decimal('debit', 14, 2)->default('0.00');   // we owe more (purchase)
            $table->decimal('credit', 14, 2)->default('0.00');  // we paid supplier
            $table->string('reference_type', 100)->nullable();  // 'purchase', 'payment', etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();

            // Composite unique index for composite FK support
            $table->unique(['shop_id', 'id']);

            // Indexes
            $table->index(['shop_id', 'supplier_id', 'created_at'], 'supp_ledger_supp_idx');
            $table->index(['shop_id', 'reference_type', 'reference_id'], 'supp_ledger_ref_idx');
            $table->index(['shop_id', 'created_at'], 'supp_ledger_created_idx');
        });

    }

    public function down(): void
    {
        Schema::dropIfExists('supplier_ledger_entries');
    }
};
