<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('locale', 10)->default('ne')->after('password');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['shop_id', 'barcode']);
            $table->unique(['shop_id', 'barcode']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('locale');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['shop_id', 'barcode']);
            $table->index(['shop_id', 'barcode']);
        });
    }
};

