<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\InvoiceSequence;
use App\Support\FiscalYear;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

class DocumentSequenceService
{
    /**
     * Generate the next gap-safe, concurrency-safe document sequence number.
     * Uses row-level lock and race-safe first insert with duplicate key retry.
     */
    public function nextNumber(int $shopId, string $prefix, ?string $fiscalYear = null): string
    {
        $fiscalYear ??= FiscalYear::current();

        return DB::transaction(function () use ($shopId, $prefix, $fiscalYear) {
            // 1. Race-safe initial upsert
            try {
                InvoiceSequence::upsert([
                    [
                        'shop_id' => $shopId,
                        'fiscal_year' => $fiscalYear,
                        'prefix' => $prefix,
                        'last_number' => 0,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                ], ['shop_id', 'fiscal_year', 'prefix'], ['prefix']);
            } catch (QueryException $e) {
                if ($e->getCode() !== '23000' && ! str_contains($e->getMessage(), '1062')) {
                    throw $e;
                }
            }

            // 2. Lock sequence row for update
            $sequence = InvoiceSequence::withoutGlobalScopes()
                ->where('shop_id', $shopId)
                ->where('fiscal_year', $fiscalYear)
                ->where('prefix', $prefix)
                ->lockForUpdate()
                ->firstOrFail();

            // 3. Increment sequence
            $nextNumber = (int) $sequence->last_number + 1;
            $sequence->update(['last_number' => $nextNumber]);

            return sprintf('%s-%s-%06d', $prefix, $fiscalYear, $nextNumber);
        });
    }
}
