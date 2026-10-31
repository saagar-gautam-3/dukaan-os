<?php

declare(strict_types=1);

namespace App\Actions\Cash;

use App\Enums\CashMovementType;
use App\Enums\CashSessionStatus;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Support\AuditTrail;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

class RecordCashMovement
{
    /**
     * Record cash movement in the currently active session if one exists.
     *
     * @param  array{
     *     type: CashMovementType|string,
     *     amount: string,
     *     reference_type?: string|null,
     *     reference_id?: int|null,
     *     notes?: string|null,
     *     branch_id?: int|null,
     * }  $data
     */
    public function handle(int $shopId, ?int $userId, array $data): ?CashMovement
    {
        $amount = (string) ($data['amount'] ?? '0.00');
        if (Money::compare($amount, '0', 2) <= 0) {
            return null;
        }

        $branchId = $data['branch_id'] ?? null;

        /** @var CashSession|null $session */
        $session = CashSession::where('shop_id', $shopId)
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', CashSessionStatus::Open->value)
            ->first();

        if (! $session) {
            return null;
        }

        $type = $data['type'] instanceof CashMovementType ? $data['type']->value : (string) $data['type'];

        return DB::transaction(function () use ($shopId, $session, $type, $amount, $data, $userId): CashMovement {
            $movement = CashMovement::create([
                'shop_id' => $shopId,
                'cash_session_id' => $session->id,
                'type' => $type,
                'amount' => $amount,
                'reference_type' => $data['reference_type'] ?? null,
                'reference_id' => $data['reference_id'] ?? null,
                'notes' => $data['notes'] ?? null,
                'created_by' => $userId,
            ]);

            AuditTrail::record($movement, $userId, 'recorded', null, [
                'cash_session_id' => $movement->cash_session_id,
                'type' => $movement->type->value,
                'amount' => $movement->amount,
                'reference_type' => $movement->reference_type,
                'reference_id' => $movement->reference_id,
            ]);

            return $movement;
        });
    }
}
