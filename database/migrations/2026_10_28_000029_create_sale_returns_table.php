<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sale_returns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to sales
            $table->foreignId('sale_id');
            $table->foreign(['shop_id', 'sale_id'])
                ->references(['shop_id', 'id'])
                ->on('sales')
                ->restrictOnDelete();

            $table->string('return_no');
            $table->string('status', 20)->default('pending'); // pending|completed|cancelled
            $table->decimal('total', 14, 2)->default('0.00');
            $table->text('reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();

            $table->unique(['shop_id', 'return_no']);
            $table->index(['shop_id', 'sale_id']);
            $table->index(['shop_id', 'status']);

            // Composite unique for tenant-safe child references
            $table->unique(['shop_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_returns');
    }
};
