<?php

declare(strict_types=1);

namespace App\Actions\Udhar;

use App\Models\Customer;
use App\Models\ReminderLog;
use App\Support\AuditTrail;
use App\Support\Money;
use App\Support\NepaliNumber;
use App\Support\NepaliPhone;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class SendWhatsAppReminder
{
    /**
     * @return array{log: ReminderLog, url: string}
     */
    public function handle(Customer $customer, int $userId): array
    {
        $balance = $customer->ledgerBalance();

        if (Money::compare($balance, '0', 2) <= 0) {
            throw new InvalidArgumentException(__('udhar.reminder_no_outstanding'));
        }

        if (! NepaliPhone::isValidMobile($customer->phone)) {
            throw new InvalidArgumentException(__('udhar.reminder_no_phone'));
        }

        $normalizedPhone = NepaliPhone::normalize($customer->phone);
        $digits = '977'.$normalizedPhone;

        $shop = TenantContext::shop();

        $oldestSale = $customer->openSales()->first();
        $dateSuffix = '';
        if ($oldestSale !== null) {
            $referenceDate = $oldestSale->due_date ?? $oldestSale->posted_at;
            if ($referenceDate !== null) {
                $dateSuffix = __('udhar.reminder_oldest_due', ['date' => $referenceDate->format('d M Y')]);
            }
        }

        $amountText = NepaliNumber::formatNpr($balance);
        $message = __('udhar.reminder_whatsapp_message', [
            'shop' => $shop->name,
            'customer' => $customer->name,
            'amount' => $amountText,
            'date' => $dateSuffix,
        ]);

        $log = DB::transaction(function () use ($shop, $customer, $userId, $balance, $message, $normalizedPhone) {
            $log = ReminderLog::create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'sent_by' => $userId,
                'channel' => 'whatsapp',
                'status' => 'opened',
                'amount' => $balance,
                'phone_masked' => ReminderLog::maskPhone($normalizedPhone),
                'message' => $message,
                'message_preview' => mb_substr($message, 0, 100),
            ]);

            AuditTrail::record($log, $userId, 'reminder_sent', null, [
                'customer_id' => $customer->id,
                'amount' => $balance,
                'channel' => 'whatsapp',
            ]);

            return $log;
        });

        return [
            'log' => $log,
            'url' => 'https://wa.me/'.$digits.'?text='.rawurlencode($message),
        ];
    }
}
