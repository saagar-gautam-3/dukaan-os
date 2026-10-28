<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_return_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to sale_returns
            $table->foreignId('sale_return_id');
            $table->foreign(['shop_id', 'sale_return_id'])
                ->references(['shop_id', 'id'])
                ->on('sale_returns')
                ->cascadeOnDelete();

            // Reference to the original sale_item
            $table->foreignId('sale_item_id')->constrained('sale_items')->restrictOnDelete();

            $table->decimal('quantity', 14, 3);
            $table->decimal('unit_cost', 14, 2);      // cost snapshot from original sale_item
            $table->decimal('refund_amount', 14, 2);  // credit back to customer

            $table->timestamps();

            $table->index(['shop_id', 'sale_return_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_return_items');
    }
};
