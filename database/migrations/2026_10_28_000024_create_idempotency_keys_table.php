<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('idempotency_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('key', 128);
            $table->string('action', 64); // e.g. 'post_sale', 'record_payment'
            $table->unsignedBigInteger('result_id')->nullable(); // The created record ID
            $table->string('result_type', 64)->nullable(); // e.g. 'sale', 'payment'
            $table->timestamps();

            $table->unique(['shop_id', 'key', 'action']);
            $table->index(['shop_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('idempotency_keys');
    }
};
