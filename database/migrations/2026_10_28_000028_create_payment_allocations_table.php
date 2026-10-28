<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_allocations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to payments
            $table->foreignId('payment_id');
            $table->foreign(['shop_id', 'payment_id'])
                ->references(['shop_id', 'id'])
                ->on('payments')
                ->cascadeOnDelete();

            // Composite FK to sales
            $table->foreignId('sale_id');
            $table->foreign(['shop_id', 'sale_id'])
                ->references(['shop_id', 'id'])
                ->on('sales')
                ->cascadeOnDelete();

            $table->decimal('amount', 14, 2);
            $table->timestamps();

            $table->index(['shop_id', 'payment_id']);
            $table->index(['shop_id', 'sale_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_allocations');
    }
};
