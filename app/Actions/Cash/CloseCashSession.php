<?php

declare(strict_types=1);

namespace App\Actions\Cash;

use App\Enums\CashSessionStatus;
use App\Models\CashSession;
use App\Support\AuditTrail;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CloseCashSession
{
    /**
     * @param  array{
     *     closing_cash: string,
     *     notes?: string|null,
     * }  $data
     */
    public function handle(CashSession $session, int $userId, array $data): CashSession
    {
        if (! $session->isOpen()) {
            throw new InvalidArgumentException(__('cash.session_already_closed'));
        }

        $closingCash = (string) ($data['closing_cash'] ?? '0.00');
        if (Money::compare($closingCash, '0', 2) < 0) {
            throw new InvalidArgumentException(__('cash.invalid_closing_amount'));
        }

        return DB::transaction(function () use ($session, $userId, $closingCash, $data) {
            /** @var CashSession $session */
            $session = CashSession::where('id', $session->id)->lockForUpdate()->firstOrFail();

            if (! $session->isOpen()) {
                throw new InvalidArgumentException(__('cash.session_already_closed'));
            }

            $oldValues = $session->only(['status', 'closing_cash', 'expected_cash', 'difference', 'closed_by', 'closed_at']);

            $expectedCash = $session->computeExpectedCash();
            $difference = Money::subtract($closingCash, $expectedCash, 2);

            $session->update([
                'status' => CashSessionStatus::Closed->value,
                'closing_cash' => $closingCash,
                'expected_cash' => $expectedCash,
                'difference' => $difference,
                'closed_by' => $userId,
                'closed_at' => now(),
                'notes' => $data['notes'] ?? $session->notes,
            ]);

            AuditTrail::record($session, $userId, 'closed', $oldValues, $session->only([
                'status', 'closing_cash', 'expected_cash', 'difference', 'closed_by', 'closed_at',
            ]));

            return $session->fresh();
        });
    }
}
