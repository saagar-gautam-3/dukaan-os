<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminder_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();

            // Composite FK to customers (tenant-safe)
            $table->unsignedBigInteger('customer_id');
            $table->foreign(['shop_id', 'customer_id'])
                ->references(['shop_id', 'id'])
                ->on('customers')
                ->cascadeOnDelete();

            $table->string('channel', 50)->default('stub'); // 'sms', 'whatsapp', 'stub'
            $table->string('status', 50)->default('sent');  // 'sent', 'failed', 'pending'
            $table->string('phone_masked', 30)->nullable();  // e.g. '98****1234' — never full number
            $table->text('message_preview')->nullable();     // first 100 chars, no financial details
            $table->string('reference_type', 100)->nullable(); // optional: 'sale', 'manual', etc.
            $table->unsignedBigInteger('reference_id')->nullable();
            $table->text('failure_reason')->nullable();
            $table->unsignedBigInteger('sent_by')->nullable(); // user who triggered
            $table->timestamps();

            $table->index(['shop_id', 'customer_id', 'created_at']);
            $table->index(['shop_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminder_logs');
    }
};
