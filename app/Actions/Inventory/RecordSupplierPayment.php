<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Cash\RecordCashMovement;
use App\Actions\Udhar\AddSupplierLedgerEntry;
use App\Enums\CashMovementType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\IdempotencyKey;
use App\Models\Supplier;
use App\Models\SupplierLedgerEntry;
use App\Support\AuditTrail;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordSupplierPayment
{
    public function __construct(
        private readonly AddSupplierLedgerEntry $addLedgerEntry,
    ) {}

    /**
     * @param  array{
     *     amount: string,
     *     method: string,
     *     reference_number?: string|null,
     *     notes?: string|null,
     *     payment_date?: string|null,
     *     idempotency_key?: string|null,
     * } $data
     */
    public function handle(Supplier $supplier, int $userId, array $data): SupplierLedgerEntry
    {
        $shop = TenantContext::shop();
        $amount = (string) ($data['amount'] ?? '0');

        if (Money::compare($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException(__('errors.supplier_payment_amount_positive'));
        }

        $idemKey = $data['idempotency_key'] ?? null;
        if ($idemKey !== null) {
            $existing = IdempotencyKey::where('shop_id', $shop->id)
                ->where('key', $idemKey)
                ->where('action', 'supplier_payment')
                ->first();

            if ($existing) {
                return SupplierLedgerEntry::findOrFail($existing->result_id);
            }
        }

        $method = PaymentMethod::from($data['method'] ?? 'cash');

        return DB::transaction(function () use ($supplier, $userId, $shop, $amount, $data, $method, $idemKey) {
            $locked = Supplier::where('id', $supplier->id)->lockForUpdate()->firstOrFail();

            $balance = $locked->ledgerBalance();
            if (Money::compare($amount, $balance, 2) > 0) {
                throw new InvalidArgumentException(__('errors.supplier_payment_exceeds_balance'));
            }

            $notes = $data['notes'] ?? null;
            if (! empty($data['payment_date'])) {
                $notes = trim(($notes ? $notes.' ' : '').'Paid on '.(string) $data['payment_date']);
            }

            $entry = $this->addLedgerEntry->handle($locked, [
                'entry_type' => LedgerEntryType::SupplierPaymentCredit,
                'credit' => $amount,
                'reference_type' => 'supplier_payment',
                'reference_id' => null,
                'notes' => $notes,
                'created_by' => $userId,
            ]);

            if ($idemKey !== null) {
                IdempotencyKey::create([
                    'shop_id' => $shop->id,
                    'user_id' => $userId,
                    'key' => $idemKey,
                    'action' => 'supplier_payment',
                    'result_id' => $entry->id,
                    'result_type' => 'supplier_ledger_entry',
                ]);
            }

            AuditTrail::record($entry, $userId, 'recorded', null, [
                'amount' => $amount,
                'method' => $method->value,
                'reference_number' => $data['reference_number'] ?? null,
            ]);

            if ($method === PaymentMethod::Cash) {
                app(RecordCashMovement::class)->handle($shop->id, $userId, [
                    'type' => CashMovementType::ExpenseCash,
                    'amount' => $amount,
                    'reference_type' => 'supplier_payment',
                    'reference_id' => $entry->id,
                    'notes' => 'Supplier payment cash',
                ]);
            }

            return $entry;
        });
    }
}
