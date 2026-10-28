<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Actions\Udhar\SendReminder;
use App\Models\Customer;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class SendReminderJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $shopId,
        public readonly int $customerId,
        public readonly ?int $sentBy = null,
        public readonly ?string $customMessage = null,
    ) {}

    public function handle(SendReminder $sendReminder): void
    {
        $shop = Shop::findOrFail($this->shopId);
        TenantContext::setShop($shop);

        $customer = Customer::findOrFail($this->customerId);

        $sendReminder->handle($customer, [
            'sent_by' => $this->sentBy,
            'message' => $this->customMessage,
        ]);
    }
}
