<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Customer;
use App\Tenancy\TenantContext;

final class ReminderMessageBuilder
{
    public static function forCustomer(Customer $customer): string
    {
        $shop = TenantContext::shop();
        $balance = $customer->ledgerBalance();

        $oldestSale = $customer->openSales()->first();
        $dateSuffix = '';
        if ($oldestSale !== null) {
            $referenceDate = $oldestSale->due_date ?? $oldestSale->posted_at;
            if ($referenceDate !== null) {
                $dateSuffix = __('udhar.reminder_oldest_due', ['date' => $referenceDate->format('d M Y')]);
            }
        }

        $amountText = NepaliNumber::formatNpr($balance);

        return __('udhar.reminder_whatsapp_message', [
            'shop' => $shop->name,
            'customer' => $customer->name,
            'amount' => $amountText,
            'date' => $dateSuffix,
        ]);
    }
}
