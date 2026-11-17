<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\SaleStatus;
use App\Jobs\SendReminderJob;
use App\Models\Customer;
use App\Models\ReminderLog;
use App\Models\Sale;
use App\Models\Shop;
use App\Support\ReminderMessageBuilder;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class SendDueRemindersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'udhar:send-due-reminders
                            {--shop= : Specific shop ID (defaults to all shops)}
                            {--dry-run : List who would be reminded without dispatching}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch reminder jobs for overdue customer sales, respecting per-shop settings and once-per-day limit.';

    public function handle(): int
    {
        $specificShopId = $this->option('shop');
        $dryRun = (bool) $this->option('dry-run');

        $shops = $specificShopId !== null
            ? Shop::where('id', (int) $specificShopId)->get()
            : Shop::all();

        if ($shops->isEmpty()) {
            $this->warn('No shops found.');

            return self::SUCCESS;
        }

        $totalDispatched = 0;

        foreach ($shops as $shop) {
            TenantContext::runAs($shop, function (Shop $shop) use (&$totalDispatched, $dryRun): void {
                $settings = $shop->settings ?? [];

                // Opt-in only: automatic reminders are OFF unless explicitly enabled.
                if (($settings['auto_reminders_enabled'] ?? false) !== true) {
                    $this->line("  [SKIP] Shop #{$shop->id} has automatic reminders disabled.");

                    return;
                }

                $shopTz = $shop->timezone ?: 'Asia/Kathmandu';
                $todayInShop = now()->setTimezone($shopTz)->toDateString();
                $startOfDayUtc = now()->setTimezone($shopTz)->startOfDay()->utc();

                // Find distinct customers with overdue sales
                $overdueCustomerIds = Sale::where('shop_id', $shop->id)
                    ->where('status', SaleStatus::Posted->value)
                    ->whereNotNull('customer_id')
                    ->whereRaw('grand_total > paid_total')
                    ->whereNotNull('due_date')
                    ->where('due_date', '<', $todayInShop)
                    ->pluck('customer_id')
                    ->unique();

                foreach ($overdueCustomerIds as $customerId) {
                    // Do not remind the same customer twice per day
                    $alreadyRemindedToday = ReminderLog::where('shop_id', $shop->id)
                        ->where('customer_id', $customerId)
                        ->where('created_at', '>=', $startOfDayUtc)
                        ->exists();

                    if ($alreadyRemindedToday) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("  [DRY-RUN] Shop #{$shop->id} would remind customer #{$customerId}.");
                        $totalDispatched++;

                        continue;
                    }

                    $customer = Customer::findOrFail((int) $customerId);
                    $message = ReminderMessageBuilder::forCustomer($customer);

                    SendReminderJob::dispatch(
                        $shop->id,
                        (int) $customerId,
                        null,
                        $message,
                        'auto',
                    );

                    $totalDispatched++;
                }
            });
        }

        if ($dryRun) {
            $this->info("Dry run: {$totalDispatched} reminder(s) would be dispatched.");
        } else {
            $this->info("Dispatched {$totalDispatched} reminder job(s) across shops.");
        }

        return self::SUCCESS;
    }
}
