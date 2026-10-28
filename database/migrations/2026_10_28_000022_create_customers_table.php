<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20)->nullable();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('pan_no', 20)->nullable();
            $table->decimal('credit_limit', 14, 2)->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Composite unique index for tenant-safe child references
            $table->unique(['shop_id', 'id']);

            // Indexes
            $table->index(['shop_id', 'phone']);
            $table->index(['shop_id', 'name']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
