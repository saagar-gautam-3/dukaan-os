<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Shop;
use App\Services\DocumentSequenceService;
use App\Tenancy\TenantContext;
use Illuminate\Console\Command;

class TestSequenceWorkerCommand extends Command
{
    protected $signature = 'test:sequence-worker
                            {shop_id : The shop ID}
                            {prefix : Sequence prefix like INV, PAY, RET}
                            {fiscal_year : Fiscal year string}
                            {--count=5 : Number of sequential numbers to request}';

    protected $description = 'Worker command to concurrently request document sequence numbers';

    public function handle(DocumentSequenceService $service): int
    {
        $shopId = (int) $this->argument('shop_id');
        $prefix = (string) $this->argument('prefix');
        $fiscalYear = (string) $this->argument('fiscal_year');
        $count = (int) $this->option('count');

        $shop = Shop::find($shopId);
        if (! $shop) {
            $this->error("Shop {$shopId} not found.");

            return self::FAILURE;
        }

        return TenantContext::runAs($shop, function () use ($service, $shopId, $prefix, $fiscalYear, $count) {
            for ($i = 0; $i < $count; $i++) {
                $number = $service->nextNumber($shopId, $prefix, $fiscalYear);
                $this->line("NUMBER:{$number}");
            }

            return self::SUCCESS;
        });
    }
}
