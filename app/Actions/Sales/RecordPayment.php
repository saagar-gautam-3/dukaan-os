<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Actions\Cash\RecordCashMovement;
use App\Actions\Udhar\AddCustomerLedgerEntry;
use App\Enums\CashMovementType;
use App\Enums\LedgerEntryType;
use App\Enums\PaymentMethod;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Sale;
use App\Services\DocumentSequenceService;
use App\Support\AuditTrail;
use App\Support\Money;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordPayment
{
    public function __construct(
        private readonly AddCustomerLedgerEntry $addLedgerEntry,
        private readonly AllocatePayment $allocatePayment,
    ) {}

    /**
     * Record a standalone payment (advance, partial, unallocated).
     *
     * @param  array{
     *     method: string,
     *     amount: string,
     *     customer_id?: int|null,
     *     reference_number?: string|null,
     *     received_at?: string|null,
     *     notes?: string|null,
     *     idempotency_key?: string|null,
     *     allocate_to_sale_id?: int|null,
     * } $data
     */
    public function handle(int $userId, array $data): Payment
    {
        $shop = TenantContext::shop();
        $amount = (string) $data['amount'];

        if (Money::compare($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Payment amount must be greater than zero.');
        }

        // Idempotency check
        $idemKey = $data['idempotency_key'] ?? null;
        if ($idemKey !== null) {
            $existing = Payment::where('shop_id', $shop->id)
                ->where('idempotency_key', $idemKey)
                ->first();

            if ($existing) {
                return $existing;
            }
        }

        $method = PaymentMethod::from($data['method']);

        return DB::transaction(function () use ($userId, $data, $shop, $method, $amount, $idemKey) {
            $paymentNo = $this->generatePaymentNumber($shop->id);

            $payment = Payment::create([
                'user_id' => $userId,
                'customer_id' => $data['customer_id'] ?? null,
                'payment_no' => $paymentNo,
                'method' => $method->value,
                'status' => $method->defaultStatus()->value,
                'amount' => $amount,
                'reference_number' => $data['reference_number'] ?? null,
                'received_at' => $data['received_at'] ?? now(),
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idemKey,
            ]);

            // Write ledger credit ONLY when NOT allocating to a specific sale.
            // When allocate_to_sale_id is set, the ledger credit belongs to RecordPayment
            // (since AllocatePayment only updates paid_total, never writes ledger).
            // Decision: RecordPayment always owns the single ledger credit write for this payment.
            // AllocatePayment is purely a bookkeeping helper (updates paid_total cache only).
            if (! empty($data['customer_id'])) {
                $customer = Customer::find($data['customer_id']);
                if ($customer) {
                    $this->addLedgerEntry->handle($customer, [
                        'entry_type' => LedgerEntryType::PaymentCredit,
                        'credit' => $amount,
                        'reference_type' => 'payment',
                        'reference_id' => $payment->id,
                        'notes' => "Payment {$payment->payment_no} recorded",
                        'created_by' => $userId,
                    ]);
                }
            }

            // Optionally allocate to a specific sale
            if (! empty($data['allocate_to_sale_id'])) {
                $this->allocatePayment->handle($payment, (int) $data['allocate_to_sale_id'], $amount);
            }

            // Record cash movement if cash was received
            if ($method === PaymentMethod::Cash) {
                app(RecordCashMovement::class)->handle($shop->id, $userId, [
                    'type' => CashMovementType::CashIn,
                    'amount' => $amount,
                    'reference_type' => 'payment',
                    'reference_id' => $payment->id,
                    'notes' => "Cash payment {$payment->payment_no}",
                ]);
            }

            AuditTrail::record($payment, $userId, 'recorded', null, [
                'payment_no' => $payment->payment_no,
                'amount' => $payment->amount,
                'method' => $payment->method->value,
                'status' => $payment->status->value,
                'customer_id' => $payment->customer_id,
                'allocations' => $payment->allocations->map(fn ($allocation): array => [
                    'sale_id' => $allocation->sale_id,
                    'amount' => $allocation->amount,
                ])->all(),
            ]);

            return $payment->refresh()->load('allocations');
        });
    }

    private function generatePaymentNumber(int $shopId): string
    {
        return app(DocumentSequenceService::class)->nextNumber($shopId, 'PAY');
    }
}
