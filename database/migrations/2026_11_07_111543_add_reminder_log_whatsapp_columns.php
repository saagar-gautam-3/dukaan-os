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
            if (! Schema::hasColumn('reminder_logs', 'amount')) {
                $table->decimal('amount', 14, 2)->nullable()->after('status');
            }

            if (! Schema::hasColumn('reminder_logs', 'message')) {
                $table->text('message')->nullable()->after('message_preview');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reminder_logs', function (Blueprint $table) {
            if (Schema::hasColumn('reminder_logs', 'amount')) {
                $table->dropColumn('amount');
            }

            if (Schema::hasColumn('reminder_logs', 'message')) {
                $table->dropColumn('message');
            }
        });
    }
};
