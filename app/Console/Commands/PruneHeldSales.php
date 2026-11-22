<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\HeldSale;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class PruneHeldSales extends Command
{
    protected $signature = 'held-sales:prune {--days=3 : Delete held bills older than this many days}';

    protected $description = 'Delete held POS bills older than N days across all shops.';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('The --days option must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $shops = Shop::query()->get();

        $deleted = 0;
        $shopsTouched = 0;

        foreach ($shops as $shop) {
            TenantContext::runAs($shop, function () use ($cutoff, &$deleted, &$shopsTouched): void {
                $count = HeldSale::query()
                    ->where('created_at', '<', $cutoff)
                    ->count();

                if ($count === 0) {
                    return;
                }

                $shopsTouched++;
                HeldSale::query()->where('created_at', '<', $cutoff)->delete();
                $deleted += $count;
            });
        }

        $this->info(sprintf(
            'Pruned %d held bill(s) across %d shop(s). Held bills older than %d day(s) are expired.',
            $deleted,
            $shopsTouched,
            $days
        ));

        return self::SUCCESS;
    }
}
