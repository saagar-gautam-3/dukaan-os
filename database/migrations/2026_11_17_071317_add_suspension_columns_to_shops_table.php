<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable()->after('is_active');
            $table->foreignId('suspended_by')->nullable()->constrained('users')->nullOnDelete()->after('suspended_at');
            $table->string('suspended_reason', 500)->nullable()->after('suspended_by');
        });

        // Backfill: verify existing platform admins so the new 'verified' gate does not lock them out.
        DB::table('users')
            ->where('is_platform_admin', true)
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        Schema::table('shops', function (Blueprint $table): void {
            $table->dropForeign(['suspended_by']);
            $table->dropColumn(['suspended_at', 'suspended_by', 'suspended_reason']);
        });
    }
};
