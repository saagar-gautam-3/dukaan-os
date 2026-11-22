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
use Carbon\Carbon;
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
     * }  $data
     */
    public function handle(Supplier $supplier, int $userId, array $data): SupplierLedgerEntry
    {
        $shop = TenantContext::shop();
        $amount = trim((string) ($data['amount'] ?? ''));

        if (! preg_match('/^\d+(?:\.\d{1,2})?$/', $amount)) {
            throw new InvalidArgumentException(__('errors.supplier_payment_amount_decimals'));
        }

        if (Money::compare($amount, '0.01', 2) < 0) {
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

        $method = PaymentMethod::tryFrom((string) ($data['method'] ?? ''));
        if ($method === null) {
            throw new InvalidArgumentException(__('errors.supplier_payment_method_invalid'));
        }

        $timezone = (string) ($shop->timezone ?: config('app.timezone'));
        $createdAt = now();
        if (! empty($data['payment_date'])) {
            $paidOn = Carbon::parse((string) $data['payment_date'], $timezone)->startOfDay();
            if ($paidOn->greaterThan(Carbon::today($timezone))) {
                throw new InvalidArgumentException(__('errors.supplier_payment_date_future'));
            }

            $createdAt = $paidOn->utc();
        }

        return DB::transaction(function () use ($supplier, $userId, $shop, $amount, $data, $method, $idemKey, $createdAt) {
            $locked = Supplier::where('id', $supplier->id)->lockForUpdate()->firstOrFail();

            $balance = $locked->ledgerBalance();
            if (Money::compare($amount, $balance, 2) > 0) {
                throw new InvalidArgumentException(__('errors.supplier_payment_exceeds_balance'));
            }

            $noteParts = [];
            if (! empty($data['notes'])) {
                $noteParts[] = trim((string) $data['notes']);
            }
            if (! empty($data['reference_number'])) {
                $noteParts[] = __('suppliers.note_reference', ['reference' => (string) $data['reference_number']]);
            }
            if (! empty($data['payment_date'])) {
                $noteParts[] = __('suppliers.note_paid_on', ['date' => (string) $data['payment_date']]);
            }

            $entry = $this->addLedgerEntry->handle($locked, [
                'entry_type' => LedgerEntryType::SupplierPaymentCredit,
                'credit' => $amount,
                'reference_type' => 'supplier_payment',
                'reference_id' => null,
                'notes' => $noteParts === [] ? null : implode(' | ', $noteParts),
                'created_by' => $userId,
                'created_at' => $createdAt,
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
                    'notes' => __('suppliers.cash_movement_note'),
                ]);
            }

            return $entry;
        });
    }
}
