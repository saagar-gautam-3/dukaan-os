<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Supplier;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

class QuerySupplierLedger
{
    /**
     * Newest-first ledger with the balance remaining after each entry.
     */
    public function handle(Supplier $supplier, int $perPage = 20): LengthAwarePaginator
    {
        $balance = $supplier->ledgerBalance();
        $timezone = (string) (TenantContext::shop()->timezone ?: config('app.timezone'));

        $entries = $supplier->ledgerEntries()
            ->with('createdBy')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(function ($entry) use (&$balance, $timezone) {
                $entry->running_balance = $balance;
                $balance = Money::subtract($balance, (string) $entry->debit, 2);
                $balance = Money::add($balance, (string) $entry->credit, 2);
                $entry->entry_date = $entry->created_at?->copy()->setTimezone($timezone)->toDateString();

                return $entry;
            });

        $page = Paginator::resolveCurrentPage();
        $items = $entries->forPage($page, $perPage)->values();

        return new LengthAwarePaginator(
            $items,
            $entries->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => request()->query()],
        );
    }
}
