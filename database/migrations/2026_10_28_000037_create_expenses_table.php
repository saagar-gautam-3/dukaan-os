<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to expense_categories (tenant-safe)
            $table->unsignedBigInteger('category_id');
            $table->foreign(['shop_id', 'category_id'])
                ->references(['shop_id', 'id'])
                ->on('expense_categories')
                ->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->date('expense_date');
            $table->string('payment_method', 20)->default('cash'); // cash|bank|qr
            $table->string('reference_number')->nullable();
            $table->text('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Composite unique index
            $table->unique(['shop_id', 'id']);

            // Indexes for fast reporting and filtering
            $table->index(['shop_id', 'expense_date']);
            $table->index(['shop_id', 'category_id']);
            $table->index(['shop_id', 'payment_method']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};
