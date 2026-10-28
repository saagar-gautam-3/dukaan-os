<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users'); // recorded by

            // Optional customer — simple FK; tenant isolation enforced at app level
            $table->foreignId('customer_id')->nullable()->constrained('customers')->nullOnDelete();

            $table->string('payment_no')->nullable();
            $table->string('method', 20); // cash|bank|qr
            $table->string('status', 20)->default('verified'); // verified|pending|failed
            $table->decimal('amount', 14, 2);
            $table->string('reference_number')->nullable(); // bank/QR reference
            $table->timestamp('received_at');
            $table->text('notes')->nullable();
            $table->string('idempotency_key', 128)->nullable();

            $table->timestamps();

            $table->unique(['shop_id', 'payment_no']);
            $table->unique(['shop_id', 'idempotency_key']);
            $table->index(['shop_id', 'method']);
            $table->index(['shop_id', 'status']);
            $table->index(['shop_id', 'customer_id']);
            $table->index(['shop_id', 'created_at']);

            // Composite unique so child tables can use composite FK (shop_id, id)
            $table->unique(['shop_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
