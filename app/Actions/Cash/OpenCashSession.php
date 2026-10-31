<?php

declare(strict_types=1);

namespace App\Actions\Cash;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Support\AuditTrail;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OpenCashSession
{
    /**
     * @param  array{
     *     opening_cash: string,
     *     branch_id?: int|null,
     *     notes?: string|null,
     * }  $data
     */
    public function handle(int $userId, array $data): CashSession
    {
        $shop = TenantContext::shop();
        $branchId = ! empty($data['branch_id']) ? (int) $data['branch_id'] : null;
        $openingCash = (string) ($data['opening_cash'] ?? '0.00');

        if (Money::compare($openingCash, '0', 2) < 0) {
            throw new InvalidArgumentException(__('cash.invalid_opening_amount'));
        }

        return DB::transaction(function () use ($shop, $userId, $branchId, $openingCash, $data) {
            // Lock and verify no open session exists for this shop/branch
            $query = CashSession::where('shop_id', $shop->id)
                ->where('status', CashSessionStatus::Open->value)
                ->lockForUpdate();

            if ($branchId !== null) {
                $query->where('branch_id', $branchId);
            }

            if ($query->exists()) {
                throw new InvalidArgumentException(__('cash.session_already_open'));
            }

            $session = CashSession::create([
                'shop_id' => $shop->id,
                'branch_id' => $branchId,
                'opened_by' => $userId,
                'opening_cash' => $openingCash,
                'status' => CashSessionStatus::Open->value,
                'opened_at' => now(),
                'notes' => $data['notes'] ?? null,
            ]);

            CashMovement::create([
                'shop_id' => $shop->id,
                'cash_session_id' => $session->id,
                'type' => CashMovementType::Opening->value,
                'amount' => $openingCash,
                'reference_type' => 'cash_session',
                'reference_id' => $session->id,
                'notes' => 'Opening Cash',
                'created_by' => $userId,
            ]);

            AuditTrail::record($session, $userId, 'opened', null, [
                'status' => $session->status->value,
                'opening_cash' => $session->opening_cash,
                'branch_id' => $session->branch_id,
                'opened_at' => $session->opened_at?->toISOString(),
            ]);

            return $session;
        });
    }
}
