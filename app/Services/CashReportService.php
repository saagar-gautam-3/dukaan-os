<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CashReportService — cash drawer and session reporting.
 *
 * Only method=cash affects the drawer.
 * Bank and QR-verified are reported separately.
 * QR in pending/failed status NEVER counts as cash received.
 */
class CashReportService
{
    /**
     * Get cash session detail with all movements.
     */
    public function sessionDetail(CashSession $session): array
    {
        $movements = $session->movements()->orderBy('created_at')->get();

        $cashIn = '0.00';
        $cashOut = '0.00';

        foreach ($movements as $m) {
            $type = $m->type instanceof CashMovementType ? $m->type : CashMovementType::from($m->type);
            if ($type === CashMovementType::Opening) {
                continue;
            }
            if ($type->isInflow()) {
                $cashIn = Money::add($cashIn, (string) $m->amount, 2);
            } else {
                $cashOut = Money::add($cashOut, (string) $m->amount, 2);
            }
        }

        $expectedCash = $session->isOpen()
            ? $session->computeExpectedCash()
            : (string) $session->expected_cash;

        return [
            'session' => $session,
            'movements' => $movements,
            'opening_cash' => (string) $session->opening_cash,
            'cash_in' => $cashIn,
            'cash_out' => $cashOut,
            'expected_cash' => $expectedCash,
            'closing_cash' => $session->closing_cash ? (string) $session->closing_cash : null,
            'difference' => $session->difference ? (string) $session->difference : null,
        ];
    }

    /**
     * Cash received breakdown for a date range (UTC).
     *
     * @param  array{from: Carbon, to: Carbon}  $range
     */
    public function cashBreakdown(int $shopId, array $range): array
    {
        $from = $range['from'];
        $to = $range['to'];

        // Cash payments only (bank and QR excluded from drawer)
        $cashPayments = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE shop_id = ?
               AND method = 'cash'
               AND status = 'verified'
               AND received_at >= ?
               AND received_at < ?",
            [$shopId, $from->toDateTimeString(), $to->toDateTimeString()]
        );

        // Bank payments
        $bankPayments = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE shop_id = ?
               AND method = 'bank'
               AND status = 'verified'
               AND received_at >= ?
               AND received_at < ?",
            [$shopId, $from->toDateTimeString(), $to->toDateTimeString()]
        );

        // Verified QR payments only
        $qrPayments = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM payments
             WHERE shop_id = ?
               AND method = 'qr'
               AND status = 'verified'
               AND received_at >= ?
               AND received_at < ?",
            [$shopId, $from->toDateTimeString(), $to->toDateTimeString()]
        );

        // Cash expenses out
        $cashExpenses = DB::selectOne(
            "SELECT COALESCE(SUM(amount), 0) AS total
             FROM expenses
             WHERE shop_id = ?
               AND payment_method = 'cash'
               AND expense_date >= ?
               AND expense_date <= ?",
            [$shopId, $from->toDateString(), $to->toDateString()]
        );

        $cashIn = (string) ($cashPayments->total ?? '0');
        $bankIn = (string) ($bankPayments->total ?? '0');
        $qrIn = (string) ($qrPayments->total ?? '0');
        $cashOut = (string) ($cashExpenses->total ?? '0');
        $totalReceived = Money::add(Money::add($cashIn, $bankIn, 2), $qrIn, 2);

        return [
            'cash' => $cashIn,
            'bank' => $bankIn,
            'qr_verified' => $qrIn,
            'total_received' => $totalReceived,
            'cash_expenses' => $cashOut,
            'net_cash' => Money::subtract($cashIn, $cashOut, 2),
        ];
    }

    /**
     * Get the current active session for the shop/branch.
     */
    public function currentSession(int $shopId, ?int $branchId = null): ?CashSession
    {
        return CashSession::where('shop_id', $shopId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', CashSessionStatus::Open->value)
            ->first();
    }
}
