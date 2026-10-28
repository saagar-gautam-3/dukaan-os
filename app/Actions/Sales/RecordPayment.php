<?php

declare(strict_types=1);

namespace App\Actions\Sales;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use App\Tenancy\TenantContext;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RecordPayment
{
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

        if (bccomp($amount, '0', 2) <= 0) {
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

            // Optionally allocate to a specific sale
            if (! empty($data['allocate_to_sale_id'])) {
                (new AllocatePayment)->handle($payment, (int) $data['allocate_to_sale_id'], $amount);
            }

            return $payment->refresh()->load('allocations');
        });
    }

    private function generatePaymentNumber(int $shopId): string
    {
        $count = Payment::where('shop_id', $shopId)->count();

        return sprintf('PAY-%06d', $count + 1);
    }
}
