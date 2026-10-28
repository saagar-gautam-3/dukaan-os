<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->string('fiscal_year', 10); // e.g. "2081-82"
            $table->string('prefix', 10)->default('INV');
            $table->unsignedBigInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['shop_id', 'fiscal_year', 'prefix']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
