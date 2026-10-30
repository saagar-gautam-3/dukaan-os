<?php

declare(strict_types=1);

use App\Services\DocumentSequenceService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $nullPayments = DB::table('payments')->whereNull('payment_no')->orderBy('id')->get();
        if ($nullPayments->isNotEmpty()) {
            $sequenceService = app(DocumentSequenceService::class);
            foreach ($nullPayments as $payment) {
                $paymentNo = $sequenceService->nextNumber((int) $payment->shop_id, 'PAY');
                DB::table('payments')->where('id', $payment->id)->update(['payment_no' => $paymentNo]);
            }
        }

        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_no')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->string('payment_no')->nullable()->change();
        });
    }
};
