<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\CustomerLedgerEntry;
use App\Models\Sale;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class LedgerService
{
    /**
     * Get a paginated list of customers with their ledger balances and open invoice counts.
     *
     * @return Collection<int, array{customer: Customer, balance: string, open_invoices: int}>
     */
    public function outstandingList(?string $search = null, int $perPage = 20): LengthAwarePaginator
    {
        $shop = TenantContext::shop();

        return Customer::where('shop_id', $shop->id)
            ->when($search, function (Builder $q, string $s): void {
                $q->where(function (Builder $q) use ($s): void {
                    $q->where('name', 'like', "%{$s}%")
                        ->orWhere('phone', 'like', "%{$s}%");
                });
            })
            ->withCount([
                'sales as open_invoices_count' => fn ($q) => $q
                    ->where('status', SaleStatus::Posted->value)
                    ->whereRaw('grand_total > paid_total'),
            ])
            ->withSum('ledgerEntries as total_debit', 'debit')
            ->withSum('ledgerEntries as total_credit', 'credit')
            ->orderByRaw('COALESCE(total_debit, 0) - COALESCE(total_credit, 0) DESC')
            ->paginate($perPage);
    }

    public function totalOutstanding(): string
    {
        return (string) CustomerLedgerEntry::where('shop_id', TenantContext::shop()->id)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) as balance')
            ->value('balance');
    }

    /**
     * Ageing report: buckets by overdue days — current, 1-30, 31-60, 61-90, 90+.
     * Uses posted_at / due_date as the reference date.
     *
     * @return array{
     *     customers: Collection,
     *     totals: array{current: string, days_30: string, days_60: string, days_90: string, over_90: string, total: string}
     * }
     */
    public function ageingReport(): array
    {
        $shop = TenantContext::shop();
        $now = now()->startOfDay();

        $customers = Customer::where('shop_id', $shop->id)
            ->with([
                'sales' => fn ($q) => $q
                    ->where('status', SaleStatus::Posted->value)
                    ->whereRaw('grand_total > paid_total')
                    ->orderBy('posted_at'),
            ])
            ->withSum('ledgerEntries as total_debit', 'debit')
            ->withSum('ledgerEntries as total_credit', 'credit')
            ->get()
            ->filter(fn ($c) => Money::compare(
                Money::subtract((string) ($c->total_debit ?? '0'), (string) ($c->total_credit ?? '0'), 2),
                '0',
                2
            ) > 0);

        $totals = ['current' => '0.00', 'days_30' => '0.00', 'days_60' => '0.00', 'days_90' => '0.00', 'over_90' => '0.00', 'total' => '0.00'];

        $result = $customers->map(function (Customer $customer) use ($now, &$totals) {
            $buckets = ['current' => '0.00', 'days_30' => '0.00', 'days_60' => '0.00', 'days_90' => '0.00', 'over_90' => '0.00'];

            foreach ($customer->sales as $sale) {
                $due = $sale->dueAmount();
                if (Money::compare($due, '0', 2) <= 0) {
                    continue;
                }

                $refDate = $sale->due_date ?? $sale->posted_at;
                $days = $refDate ? $now->diffInDays($refDate, false) : 0;
                // diffInDays is negative when overdue
                $overdueDays = $days >= 0 ? 0 : abs((int) $days);

                $bucket = match (true) {
                    $overdueDays === 0 => 'current',
                    $overdueDays <= 30 => 'days_30',
                    $overdueDays <= 60 => 'days_60',
                    $overdueDays <= 90 => 'days_90',
                    default => 'over_90',
                };

                $buckets[$bucket] = Money::add($buckets[$bucket], $due, 2);
                $totals[$bucket] = Money::add($totals[$bucket], $due, 2);
                $totals['total'] = Money::add($totals['total'], $due, 2);
            }

            $customer->ageing_buckets = $buckets;

            return $customer;
        });

        return ['customers' => $result, 'totals' => $totals];
    }

    /**
     * Ledger balance for a customer from the source-of-truth entries.
     */
    public function customerBalance(Customer $customer): string
    {
        $debit = CustomerLedgerEntry::where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->id)
            ->sum('debit');

        $credit = CustomerLedgerEntry::where('shop_id', $customer->shop_id)
            ->where('customer_id', $customer->id)
            ->sum('credit');

        return Money::subtract((string) $debit, (string) $credit, 2);
    }

    /**
     * Ledger reconciliation: compare cached balance (sum from sale fields) vs ledger sum.
     * Returns an array of [customer_id, ledger_balance, cached_balance, drift] for each drifting customer.
     *
     * @return array<int, array{customer_id: int, ledger_balance: string, cached_balance: string, drift: string}>
     */
    public function reconcile(int $shopId): array
    {
        // Customer balance from ledger entries
        $ledgerBalances = CustomerLedgerEntry::where('shop_id', $shopId)
            ->select('customer_id', DB::raw('SUM(debit) - SUM(credit) as ledger_balance'))
            ->groupBy('customer_id')
            ->pluck('ledger_balance', 'customer_id');

        // Cached balance = posted sale grand_totals - paid_totals (sale-level cache)
        $cachedBalances = Sale::where('shop_id', $shopId)
            ->where('status', SaleStatus::Posted->value)
            ->whereNotNull('customer_id')
            ->select('customer_id', DB::raw('SUM(grand_total) - SUM(paid_total) as cached_balance'))
            ->groupBy('customer_id')
            ->pluck('cached_balance', 'customer_id');

        $allCustomerIds = $ledgerBalances->keys()->merge($cachedBalances->keys())->unique();

        $drifts = [];
        foreach ($allCustomerIds as $customerId) {
            $ledger = (string) ($ledgerBalances->get($customerId) ?? '0.00');
            $cached = (string) ($cachedBalances->get($customerId) ?? '0.00');
            $drift = Money::subtract($ledger, $cached, 2);

            if (Money::compare($drift, '0', 2) !== 0) {
                $drifts[] = [
                    'customer_id' => (int) $customerId,
                    'ledger_balance' => $ledger,
                    'cached_balance' => $cached,
                    'drift' => $drift,
                ];
            }
        }

        return $drifts;
    }
}
