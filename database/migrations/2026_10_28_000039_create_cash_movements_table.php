<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to cash_sessions (tenant-safe)
            $table->unsignedBigInteger('cash_session_id');
            $table->foreign(['shop_id', 'cash_session_id'])
                ->references(['shop_id', 'id'])
                ->on('cash_sessions')
                ->cascadeOnDelete();

            $table->string('type', 30); // opening|cash_in|cash_out|sale_cash|expense_cash|collection_cash
            $table->decimal('amount', 14, 2);
            $table->string('reference_type', 100)->nullable();
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // Composite unique index
            $table->unique(['shop_id', 'id']);

            // Indexes
            $table->index(['shop_id', 'cash_session_id']);
            $table->index(['shop_id', 'type']);
            $table->index(['shop_id', 'reference_type', 'reference_id']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_movements');
    }
};
