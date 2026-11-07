<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('user_id')->nullable()->after('customer_id');
            $table->decimal('amount', 14, 2)->nullable()->after('status');
            $table->text('message')->nullable()->after('message_preview');

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropColumn(['user_id', 'amount', 'message']);
        });
    }
};
