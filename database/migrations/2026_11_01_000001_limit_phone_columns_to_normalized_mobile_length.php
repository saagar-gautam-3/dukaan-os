<?php

declare(strict_types=1);

use App\Support\NepaliPhone;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['users', 'customers', 'suppliers'] as $table) {
            foreach (DB::table($table)->whereNotNull('phone')->get(['id', 'phone']) as $record) {
                if (! NepaliPhone::isValidMobile($record->phone)) {
                    throw new RuntimeException("Cannot shrink {$table}.phone: record {$record->id} is not a valid Nepal mobile number.");
                }

                $normalized = NepaliPhone::normalize($record->phone);
                if ($normalized !== $record->phone) {
                    DB::table($table)->where('id', $record->id)->update(['phone' => $normalized]);
                }
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('phone', 10)->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (['users', 'customers', 'suppliers'] as $table) {
            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->string('phone', 20)->nullable()->change();
            });
        }
    }
};
