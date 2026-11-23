<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Enums\LedgerEntryType;
use App\Models\Supplier;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class QuerySupplierDues
{
    /**
     * @return array{suppliers: Collection<int, Supplier>, total_payable: string}
     */
    public function handle(): array
    {
        $timezone = (string) (TenantContext::shop()->timezone ?: config('app.timezone'));

        $suppliers = Supplier::query()
            ->withSum('ledgerEntries as debit_sum', 'debit')
            ->withSum('ledgerEntries as credit_sum', 'credit')
            ->withMax(['ledgerEntries as last_payment_at' => function ($q): void {
                $q->where('entry_type', LedgerEntryType::SupplierPaymentCredit->value);
            }], 'created_at')
            ->withMin(['ledgerEntries as oldest_purchase_at' => function ($q): void {
                $q->where('entry_type', LedgerEntryType::PurchaseDebit->value);
            }], 'created_at')
            ->get()
            ->map(function (Supplier $supplier) use ($timezone): Supplier {
                $supplier->balance = Money::subtract(
                    (string) ($supplier->debit_sum ?? '0'),
                    (string) ($supplier->credit_sum ?? '0'),
                    2,
                );

                // Ledger timestamps are stored in UTC; show calendar dates in the shop's timezone.
                $supplier->oldest_purchase_date = $supplier->oldest_purchase_at === null
                    ? null
                    : Carbon::parse((string) $supplier->oldest_purchase_at, 'UTC')->setTimezone($timezone)->toDateString();
                $supplier->last_payment_date = $supplier->last_payment_at === null
                    ? null
                    : Carbon::parse((string) $supplier->last_payment_at, 'UTC')->setTimezone($timezone)->toDateString();

                return $supplier;
            })
            ->filter(fn (Supplier $supplier): bool => Money::compare((string) $supplier->balance, '0', 2) > 0)
            ->sortByDesc(fn (Supplier $supplier): string => (string) $supplier->balance)
            ->values();

        $totalPayable = $suppliers->reduce(
            fn (string $carry, Supplier $supplier): string => Money::add($carry, (string) $supplier->balance, 2),
            '0.00',
        );

        return [
            'suppliers' => $suppliers,
            'total_payable' => $totalPayable,
        ];
    }
}
