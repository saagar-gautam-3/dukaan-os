<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\PaymentStatus;
use App\Enums\SaleStatus;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ProfitReportService — implements definitions from Docs/01-project-overview.md exactly:
 *
 * - Sales amount (बिक्री): selling value of goods sold (grand_total of posted sales, excluding tax)
 * - Cash received (नगद प्राप्ति): money actually received (cash/bank/qr-verified) in the period
 * - Gross profit: net sales − COGS (COGS from sale_items.unit_cost snapshots)
 * - Net profit: gross profit − operating expenses
 * - Customer outstanding (उधार बाँकी): from ledger (SUM debit − SUM credit)
 *
 * ALL date boundaries are converted to UTC using the shop's timezone.
 */
class ProfitReportService
{
    /**
     * @param  array{from: Carbon, to: Carbon}  $range  UTC range
     */
    public function summary(int $shopId, array $range): array
    {
        $from = $range['from'];
        $to = $range['to'];

        // 1. Sales amounts (net of tax) — only posted sales
        $salesData = DB::selectOne(
            'SELECT
                COALESCE(SUM(grand_total), 0)   AS gross_sales,
                COALESCE(SUM(tax_total), 0)      AS tax_total,
                COALESCE(SUM(grand_total - COALESCE(tax_total,0)), 0) AS net_sales,
                COALESCE(SUM(paid_total), 0)     AS paid_total_cache,
                COUNT(*)                         AS sale_count
             FROM sales
             WHERE shop_id = ?
               AND status = ?
               AND posted_at >= ?
               AND posted_at < ?',
            [$shopId, SaleStatus::Posted->value, $from->toDateTimeString(), $to->toDateTimeString()]
        );

        $grossSales = (string) ($salesData->gross_sales ?? '0');
        $taxTotal = (string) ($salesData->tax_total ?? '0');
        $netSales = (string) ($salesData->net_sales ?? '0');
        $saleCount = (int) ($salesData->sale_count ?? 0);

        // 2. COGS from sale_items.unit_cost (snapshot at posting time)
        //    Returns negative for returns (they reduce COGS)
        $cogsData = DB::selectOne(
            'SELECT COALESCE(SUM(si.unit_cost * si.quantity), 0) AS cogs
             FROM sale_items si
             INNER JOIN sales s ON s.id = si.sale_id
             WHERE s.shop_id = ?
               AND s.status = ?
               AND s.posted_at >= ?
               AND s.posted_at < ?',
            [$shopId, SaleStatus::Posted->value, $from->toDateTimeString(), $to->toDateTimeString()]
        );
        $cogs = (string) ($cogsData->cogs ?? '0');

        // 3. COGS reversal from returns (unit_cost is stored on sale_return_items)
        $returnsCogsData = DB::selectOne(
            "SELECT COALESCE(SUM(sri.unit_cost * sri.quantity), 0) AS return_cogs
             FROM sale_return_items sri
             INNER JOIN sale_returns sr ON sr.id = sri.sale_return_id
             INNER JOIN sales s ON s.id = sr.sale_id
             WHERE s.shop_id = ?
               AND sr.status = 'completed'
               AND sr.created_at >= ?
               AND sr.created_at < ?",
            [$shopId, $from->toDateTimeString(), $to->toDateTimeString()]
        );
        $returnCogs = (string) ($returnsCogsData->return_cogs ?? '0');

        // 4. Net return amount (reduces sales revenue)
        $returnAmountData = DB::selectOne(
            "SELECT COALESCE(SUM(sr.total), 0) AS return_total
             FROM sale_returns sr
             INNER JOIN sales s ON s.id = sr.sale_id
             WHERE s.shop_id = ?
               AND sr.status = 'completed'
               AND sr.created_at >= ?
               AND sr.created_at < ?",
            [$shopId, $from->toDateTimeString(), $to->toDateTimeString()]
        );
        $returnTotal = (string) ($returnAmountData->return_total ?? '0');

        // Net sales after returns
        $netSalesAfterReturns = Money::subtract($netSales, $returnTotal, 2);

        // Net COGS after returns
        $netCogs = Money::subtract($cogs, $returnCogs, 2);

        // 5. Gross profit = net_sales_after_returns − net_cogs
        $grossProfit = Money::subtract($netSalesAfterReturns, $netCogs, 2);

        // 6. Operating expenses in the period
        $expenseData = DB::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS total_expenses
             FROM expenses
             WHERE shop_id = ?
               AND expense_date >= ?
               AND expense_date <= ?',
            [$shopId, $from->toDateString(), $to->toDateString()]
        );
        $totalExpenses = (string) ($expenseData->total_expenses ?? '0');

        // 7. Net profit
        $netProfit = Money::subtract($grossProfit, $totalExpenses, 2);

        // 8. Cash received = verified cash/bank/QR payments in period
        //    QR pending/failed do NOT count (rule: only verified)
        $cashReceivedData = DB::selectOne(
            'SELECT COALESCE(SUM(amount), 0) AS cash_received
             FROM payments
             WHERE shop_id = ?
               AND status = ?
               AND received_at >= ?
               AND received_at < ?',
            [$shopId, PaymentStatus::Verified->value, $from->toDateTimeString(), $to->toDateTimeString()]
        );
        $cashReceived = (string) ($cashReceivedData->cash_received ?? '0');

        // 9. Outstanding (from ledger) — point-in-time, not date-bounded
        $outstandingData = DB::selectOne(
            'SELECT COALESCE(SUM(debit) - SUM(credit), 0) AS outstanding
             FROM customer_ledger_entries
             WHERE shop_id = ?',
            [$shopId]
        );
        $outstanding = (string) ($outstandingData->outstanding ?? '0');

        return [
            'gross_sales' => Money::round($grossSales, 2),
            'net_sales' => Money::round($netSalesAfterReturns, 2),
            'tax_total' => Money::round($taxTotal, 2),
            'return_total' => Money::round($returnTotal, 2),
            'cogs' => Money::round($netCogs, 2),
            'gross_profit' => Money::round($grossProfit, 2),
            'total_expenses' => Money::round($totalExpenses, 2),
            'net_profit' => Money::round($netProfit, 2),
            'cash_received' => Money::round($cashReceived, 2),
            'outstanding' => Money::round($outstanding, 2),
            'sale_count' => $saleCount,
            'period_from' => $from->toIso8601String(),
            'period_to' => $to->toIso8601String(),
        ];
    }

    /**
     * Resolve a named range (today|this_week|this_month|custom) to a UTC [from, to] pair
     * using the shop's timezone so that day boundaries are correct.
     *
     * @return array{from: Carbon, to: Carbon}
     */
    public function resolveDateRange(
        string $range,
        string $shopTimezone,
        ?string $customFrom = null,
        ?string $customTo = null
    ): array {
        $tz = $shopTimezone ?: 'Asia/Kathmandu';
        $now = Carbon::now($tz);

        return match ($range) {
            'today' => [
                'from' => $now->copy()->startOfDay()->utc(),
                'to' => $now->copy()->endOfDay()->utc(),
            ],
            'this_week' => [
                'from' => $now->copy()->startOfWeek()->utc(),
                'to' => $now->copy()->endOfWeek()->utc(),
            ],
            'this_month' => [
                'from' => $now->copy()->startOfMonth()->utc(),
                'to' => $now->copy()->endOfMonth()->utc(),
            ],
            'custom' => [
                'from' => Carbon::parse($customFrom, $tz)->startOfDay()->utc(),
                'to' => Carbon::parse($customTo, $tz)->endOfDay()->utc(),
            ],
            default => [
                'from' => $now->copy()->startOfDay()->utc(),
                'to' => $now->copy()->endOfDay()->utc(),
            ],
        };
    }

    /**
     * Stock valuation using weighted average cost from stock_balances.
     */
    public function stockValuation(int $shopId): array
    {
        $rows = DB::select(
            'SELECT p.id, p.name, p.sku,
                    COALESCE(sb.quantity, 0)      AS qty,
                    COALESCE(sb.average_cost, 0)  AS avg_cost,
                    COALESCE(sb.quantity, 0) * COALESCE(sb.average_cost, 0) AS value
             FROM products p
             LEFT JOIN stock_balances sb ON sb.product_id = p.id AND sb.shop_id = p.shop_id
             WHERE p.shop_id = ?
               AND p.track_stock = 1
             ORDER BY value DESC',
            [$shopId]
        );

        $totalValue = '0.00';
        foreach ($rows as $row) {
            $totalValue = Money::add($totalValue, (string) $row->value, 2);
        }

        return [
            'items' => $rows,
            'total_value' => $totalValue,
        ];
    }
}
