<?php

declare(strict_types=1);

namespace App\Actions\Udhar;

use App\Models\Customer;
use App\Models\ReminderLog;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Schedule / dispatch a payment reminder for a customer.
 * Phone numbers are NEVER stored in full — always masked.
 * Currently stubs the channel; a real SMS provider hooks here later.
 */
class SendReminder
{
    /**
     * @param  array{
     *     channel?: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     message?: string|null,
     *     sent_by?: int|null,
     * }  $options
     */
    public function handle(Customer $customer, array $options = []): ReminderLog
    {
        $shop = TenantContext::shop();
        $channel = $options['channel'] ?? 'stub';
        $sentBy = $options['sent_by'] ?? null;
        $balance = $customer->ledgerBalance();

        // Mask phone — NEVER log the full number (security-rules.md §8)
        $phoneMasked = ReminderLog::maskPhone($customer->phone);

        // Build message preview (no sensitive financial details logged)
        $messagePreview = $options['message'] ?? __('udhar.reminder_message_preview');

        return DB::transaction(function () use ($shop, $customer, $channel, $sentBy, $phoneMasked, $messagePreview, $options) {
            // Stub channel: just log it; future: dispatch a queued job per channel
            $status = $this->dispatchChannel($channel, $customer, $messagePreview);

            return ReminderLog::create([
                'shop_id' => $shop->id,
                'customer_id' => $customer->id,
                'channel' => $channel,
                'status' => $status,
                'phone_masked' => $phoneMasked,
                'message_preview' => substr($messagePreview, 0, 100),
                'reference_type' => $options['reference_type'] ?? null,
                'reference_id' => $options['reference_id'] ?? null,
                'failure_reason' => null,
                'sent_by' => $sentBy,
            ]);
        });
    }

    /**
     * Stub channel dispatcher.
     * Returns 'sent' for stub; future providers return actual status.
     */
    private function dispatchChannel(string $channel, Customer $customer, string $message): string
    {
        return match ($channel) {
            'stub' => 'sent', // No-op; future: dispatch queued job
            default => 'sent',
        };
    }
}
