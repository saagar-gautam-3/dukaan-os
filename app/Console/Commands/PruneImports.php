<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\ProductImport;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

final class PruneImports extends Command
{
    protected $signature = 'imports:prune {--days=7 : Delete import files older than this many days}';

    protected $description = 'Delete stored product import files older than N days (keeps history rows).';

    public function handle(): int
    {
        $days = (int) $this->option('days');

        if ($days < 1) {
            $this->error('The --days option must be at least 1.');

            return self::FAILURE;
        }

        $cutoff = now()->subDays($days);
        $shops = Shop::query()->get();

        $filesDeleted = 0;
        $rowsCleared = 0;
        $shopsTouched = 0;

        foreach ($shops as $shop) {
            TenantContext::runAs($shop, function () use ($cutoff, &$filesDeleted, &$rowsCleared, &$shopsTouched): void {
                $imports = ProductImport::query()
                    ->where('created_at', '<', $cutoff)
                    ->whereNotNull('stored_path')
                    ->get();

                if ($imports->isEmpty()) {
                    return;
                }

                $shopsTouched++;

                foreach ($imports as $import) {
                    $path = (string) $import->stored_path;

                    $fullPath = storage_path('app/'.$path);

                    if (is_file($fullPath)) {
                        @unlink($fullPath);
                        $filesDeleted++;
                    }

                    // Clear the reference but keep the history row.
                    $import->stored_path = null;
                    $import->save();
                    $rowsCleared++;
                }
            });
        }

        $this->info(sprintf(
            'Pruned %d import file(s) across %d shop(s); cleared %d row reference(s). Imports older than %d day(s) are expired.',
            $filesDeleted,
            $shopsTouched,
            $rowsCleared,
            $days
        ));

        return self::SUCCESS;
    }
}
