<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Customer;
use App\Models\Shop;
use App\Services\LedgerService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class LedgerReconcile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'ledger:reconcile
                            {--shop= : Specific shop ID to reconcile (defaults to all shops)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reconcile customer ledger entries with sale-level balances and report any drifts.';

    public function handle(LedgerService $ledgerService): int
    {
        $specificShopId = $this->option('shop');

        $shops = $specificShopId !== null
            ? Shop::where('id', (int) $specificShopId)->get()
            : Shop::all();

        if ($shops->isEmpty()) {
            $this->warn('No shops found to reconcile.');

            return self::SUCCESS;
        }

        $totalDrifts = 0;

        foreach ($shops as $shop) {
            $this->info("Reconciling shop #{$shop->id} ({$shop->name})...");

            TenantContext::runAs($shop, function () use ($shop, $ledgerService, &$totalDrifts) {
                $drifts = $ledgerService->reconcile((int) $shop->id);

                if (empty($drifts)) {
                    $this->info("  [OK] Shop #{$shop->id}: All customer balances match ledger sums.");

                    return;
                }

                $totalDrifts += count($drifts);
                $this->error('  [DRIFT DETECTED] Found '.count($drifts)." drifting customer balance(s) in shop #{$shop->id}:");

                $rows = [];
                foreach ($drifts as $drift) {
                    $customer = Customer::withoutGlobalScopes()->find($drift['customer_id']);
                    $rows[] = [
                        'Customer ID' => $drift['customer_id'],
                        'Customer Name' => $customer?->name ?? 'Unknown',
                        'Ledger Balance' => $drift['ledger_balance'],
                        'Cached Balance' => $drift['cached_balance'],
                        'Drift' => $drift['drift'],
                    ];
                }

                $this->table(['Customer ID', 'Customer Name', 'Ledger Balance', 'Cached Balance', 'Drift'], $rows);
            });
        }

        if ($totalDrifts > 0) {
            $this->error("Reconciliation finished with {$totalDrifts} drift(s) found across shops.");

            return self::FAILURE;
        }

        $this->info('Reconciliation completed: All shops are in balance.');

        return self::SUCCESS;
    }
}
