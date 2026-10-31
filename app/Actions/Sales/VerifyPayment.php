<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\PaymentStatus;
use App\Models\AuditLog;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VerifyPayment
{
    public function handle(Payment $payment, int $userId): Payment
    {
        return DB::transaction(function () use ($payment, $userId): Payment {
            $payment->refresh();

            if ($payment->status !== PaymentStatus::Pending) {
                throw new InvalidArgumentException('Only pending payments can be verified.');
            }

            $oldStatus = $payment->status->value;
            $payment->status = PaymentStatus::Verified;
            $payment->save();

            AuditLog::create([
                'shop_id' => $payment->shop_id,
                'user_id' => $userId,
                'event' => 'verified',
                'auditable_type' => $payment->getMorphClass(),
                'auditable_id' => $payment->getKey(),
                'old_values' => ['status' => $oldStatus],
                'new_values' => ['status' => PaymentStatus::Verified->value],
                'ip_address' => request()?->ip(),
                'user_agent' => request()?->userAgent(),
            ]);

            return $payment->refresh();
        });
    }
}
