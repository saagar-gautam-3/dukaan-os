<?php

declare(strict_types=1);

namespace App\Actions\Reports;

use App\Enums\LedgerEntryType;
use App\Enums\PaymentStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SaleStatus;
use App\Models\Expense;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Shop;
use App\Services\CashReportService;
use App\Services\ProfitReportService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * One query per section, tenant scoped by shop id, bcmath strings only.
 *
 * Date semantics (both ProfitReportService and here): the window is the
 * shop-local day [00:00, next day 00:00) in the shop's timezone, expanded
 * to UTC for timestamp columns. expense_date is a plain DATE, so it is
 * compared to the shop-local date string directly.
 */
final class QueryDailySummary
{
    public function __construct(
        private readonly ProfitReportService $profitService,
        private readonly CashReportService $cashService,
    ) {}

    /**
     * @return array{sales: array, udhar: array, expenses: array, purchases: array, cash: array,
     *               stock: array, top_products: array}
     */
    public function handle(Shop $shop, ?string $date = null): array
    {
        $range = $this->profitService->dayRange((string) ($shop->timezone ?: 'Asia/Kathmandu'), $date);
        [$from, $to] = [$range['from'], $range['to']];
        $localDate = $range['from']->copy()->setTimezone($shop->timezone ?: 'Asia/Kathmandu')->toDateString();

        $summary = $this->profitService->summary($shop->id, $range);

        return [
            'sales' => $this->sales($shop, $from, $to, $summary),
            'udhar' => $this->udhar($shop, $from, $to),
            'expenses' => $this->expenses($shop, $localDate),
            'purchases' => $this->purchases($shop, $from, $to),
            'cash' => $this->cash($shop, $from, $to),
            'stock' => $this->stock($shop),
            'top_products' => $this->topProducts($shop, $from, $to),
        ];
    }

    private function sales(Shop $shop, $from, $to, array $summary): array
    {
        // Method split sums the allocations recorded against today's posted
        // sales, so the split ties to the sale day (not the collection day).
        $rows = DB::select(
            'SELECT p.method AS method, COALESCE(SUM(pa.amount), 0) AS total, COUNT(*) AS payments
             FROM payment_allocations pa
             INNER JOIN payments p ON p.id = pa.payment_id AND p.shop_id = pa.shop_id
             INNER JOIN sales s ON s.id = pa.sale_id AND s.shop_id = pa.shop_id
             WHERE pa.shop_id = ?
               AND s.status = ?
               AND s.posted_at >= ?
               AND s.posted_at < ?
               AND p.status = ?
             GROUP BY p.method',
            [$shop->id, SaleStatus::Posted->value, $from->toDateTimeString(), $to->toDateTimeString(), PaymentStatus::Verified->value]
        );

        $cash = '0.00';
        $digital = '0.00';
        foreach ($rows as $row) {
            $amount = (string) $row->total;

            if ($row->method === 'cash') {
                $cash = Money::add($cash, $amount, 2);
            } else {
                $digital = Money::add($digital, $amount, 2);
            }
        }

        $total = (string) $summary['gross_sales'];
        $credit = Money::subtract(Money::subtract($total, $cash, 2), $digital, 2);

        $returns = DB::selectOne(
            "SELECT COUNT(*) AS return_count, COALESCE(SUM(sr.total), 0) AS return_amount
             FROM sale_returns sr
             INNER JOIN sales s ON s.id = sr.sale_id AND s.shop_id = sr.shop_id
             WHERE sr.shop_id = ?
               AND sr.status = 'completed'
               AND sr.created_at >= ?
               AND sr.created_at < ?",
            [$shop->id, $from->toDateTimeString(), $to->toDateTimeString()]
        );

        $returnAmount = (string) ($returns->return_amount ?? '0.00');

        return [
            'bill_count' => (int) $summary['sale_count'],
            'total' => $total,
            'cash' => $cash,
            'digital' => $digital,
            'credit' => $credit,
            'returns_count' => (int) ($returns->return_count ?? 0),
            'returns_amount' => $returnAmount,
            'net_sales' => Money::subtract($total, $returnAmount, 2),
            'gross_profit' => (string) $summary['gross_profit'],
        ];
    }

    private function udhar(Shop $shop, $from, $to): array
    {
        // Net new due from today’s posted sales: sale-debit entries minus the
        // at-POS payment credits linked to those sales (full-cash sales net
        // to zero; walk-in sales write no ledger entries at all).
        $postedSaleIds = DB::table('sales')
            ->where('shop_id', $shop->id)
            ->where('status', SaleStatus::Posted->value)
            ->where('posted_at', '>=', $from->toDateTimeString())
            ->where('posted_at', '<', $to->toDateTimeString())
            ->pluck('id');

        $givenRows = DB::table('customer_ledger_entries')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString())
            ->where('reference_type', 'sale')
            ->whereIn('reference_id', $postedSaleIds)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) AS amount')
            ->first();

        $collectedRows = DB::table('customer_ledger_entries')
            ->where('shop_id', $shop->id)
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString())
            ->whereIn('entry_type', [LedgerEntryType::PaymentCredit->value, LedgerEntryType::AdvanceCredit->value])
            ->where(function ($query) use ($postedSaleIds): void {
                $query->where('reference_type', '!=', 'sale')
                    ->orWhereNotIn('reference_id', $postedSaleIds);
            })
            ->selectRaw('COALESCE(SUM(credit), 0) AS amount')
            ->first();

        $returnsCredit = DB::table('customer_ledger_entries')
            ->where('shop_id', $shop->id)
            ->where('entry_type', LedgerEntryType::ReturnCredit->value)
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString())
            ->selectRaw('COALESCE(SUM(credit), 0) AS amount')
            ->first();

        $receivableNow = DB::table('customer_ledger_entries')
            ->where('shop_id', $shop->id)
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) AS amount')
            ->value('amount') ?? '0.00';

        $receivableStart = DB::table('customer_ledger_entries')
            ->where('shop_id', $shop->id)
            ->where('created_at', '<', $from->toDateTimeString())
            ->selectRaw('COALESCE(SUM(debit) - SUM(credit), 0) AS amount')
            ->value('amount') ?? '0.00';

        return [
            'given' => Money::round((string) ($givenRows->amount ?? '0.00'), 2),
            'collected' => Money::round((string) ($collectedRows->amount ?? '0.00'), 2),
            'returns' => Money::round((string) ($returnsCredit->amount ?? '0.00'), 2),
            'receivable_start' => Money::round((string) $receivableStart, 2),
            'receivable_now' => Money::round((string) $receivableNow, 2),
        ];
    }

    private function expenses(Shop $shop, string $localDate): array
    {
        $total = Expense::where('shop_id', $shop->id)
            ->where('expense_date', $localDate)
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount')
            ->value('amount') ?? '0.00';

        $top = Expense::where('shop_id', $shop->id)
            ->where('expense_date', $localDate)
            ->selectRaw('category_id, COALESCE(SUM(amount), 0) AS total')
            ->groupBy('category_id')
            ->orderByDesc('total')
            ->limit(3)
            ->with('category')
            ->get()
            ->map(fn (Expense $expense): array => [
                'name' => $expense->category?->name ?? '',
                'total' => Money::round((string) $expense->total, 2),
            ])
            ->all();

        return [
            'total' => Money::round((string) $total, 2),
            'top_categories' => $top,
        ];
    }

    private function purchases(Shop $shop, $from, $to): array
    {
        $total = Purchase::where('shop_id', $shop->id)
            ->where('status', PurchaseStatus::Posted->value)
            ->where('posted_at', '>=', $from->toDateTimeString())
            ->where('posted_at', '<', $to->toDateTimeString())
            ->selectRaw('COALESCE(SUM(grand_total), 0) AS amount')
            ->value('amount') ?? '0.00';

        return ['total' => Money::round((string) $total, 2)];
    }

    private function cash(Shop $shop, $from, $to): array
    {
        $in = DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereIn('type', ['sale_cash', 'collection_cash'])
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString())
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount')
            ->value('amount') ?? '0.00';

        $out = DB::table('cash_movements')
            ->where('shop_id', $shop->id)
            ->whereIn('type', ['expense_cash', 'cash_out'])
            ->where('created_at', '>=', $from->toDateTimeString())
            ->where('created_at', '<', $to->toDateTimeString())
            ->selectRaw('COALESCE(SUM(amount), 0) AS amount')
            ->value('amount') ?? '0.00';

        $in = Money::round((string) $in, 2);
        $out = Money::round((string) $out, 2);

        $session = $this->cashService->currentSession($shop->id);

        return [
            'in' => $in,
            'out' => $out,
            'net' => Money::subtract($in, $out, 2),
            'open_drawer_balance' => $session !== null ? Money::round($session->computeExpectedCash(), 2) : null,
        ];
    }

    private function stock(Shop $shop): array
    {
        $products = Product::with(['unit', 'stockBalances'])
            ->where('shop_id', $shop->id)
            ->where('is_active', true)
            ->where('low_stock_threshold', '>', 0)
            ->get()
            ->map(function (Product $product): array {
                $current = $product->stockBalances->reduce(
                    fn (string $sum, $balance): string => Money::add($sum, (string) $balance->quantity, 3),
                    '0.000'
                );
                $threshold = (string) $product->low_stock_threshold;

                return [
                    'product' => $product,
                    'current' => $current,
                    'threshold' => $threshold,
                    'shortfall' => Money::subtract($threshold, $current, 3),
                ];
            })
            ->filter(fn (array $row): bool => Money::compare($row['shortfall'], '0', 3) >= 0)
            ->sort(fn (array $a, array $b): int => Money::compare($b['shortfall'], $a['shortfall'], 3))
            ->values();

        return [
            'low_stock_count' => $products->count(),
            'most_urgent' => $products->take(5)->all(),
        ];
    }

    private function topProducts(Shop $shop, $from, $to): array
    {
        return DB::table('sale_items as si')
            ->join('sales as s', function ($join): void {
                $join->on('s.id', '=', 'si.sale_id')->whereColumn('s.shop_id', 'si.shop_id');
            })
            ->join('products as p', function ($join): void {
                $join->on('p.id', '=', 'si.product_id')->whereColumn('p.shop_id', 'si.shop_id');
            })
            ->where('si.shop_id', $shop->id)
            ->where('s.status', SaleStatus::Posted->value)
            ->where('s.posted_at', '>=', $from->toDateTimeString())
            ->where('s.posted_at', '<', $to->toDateTimeString())
            ->selectRaw('p.id, p.name, COALESCE(SUM(si.quantity), 0) AS qty_sold, COALESCE(SUM(si.line_total), 0) AS amount')
            ->groupBy('p.id', 'p.name')
            ->orderByDesc('qty_sold')
            ->limit(5)
            ->get()
            ->map(fn ($row): array => [
                'name' => (string) $row->name,
                'qty' => Money::round((string) $row->qty_sold, 3),
                'amount' => Money::round((string) $row->amount, 2),
            ])
            ->all();
    }
}
