<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\ImmutableRecordException;
use App\Support\Money;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToShop, HasFactory;

    /**
     * Class C: core financial fields are frozen from the moment the payment is
     * created. Only the status may transition (pending→verified or
     * pending→failed); verified/failed are terminal. Payments are never deleted
     * — use a reversal entry instead.
     */
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            $frozenColumns = ['amount', 'method', 'payment_no', 'shop_id', 'customer_id'];
            foreach ($frozenColumns as $col) {
                if ($model->isDirty($col)) {
                    throw new ImmutableRecordException("Payment field '{$col}' is frozen and cannot be changed.");
                }
            }

            $originalStatus = PaymentStatus::tryFrom((string) $model->getRawOriginal('status'));
            $newStatus = $model->status;
            if (
                $originalStatus !== null &&
                $originalStatus !== PaymentStatus::Pending &&
                $newStatus !== $originalStatus
            ) {
                throw new ImmutableRecordException('Verified or failed payment status is terminal and cannot be changed.');
            }
        });

        static::deleting(function (): never {
            throw new ImmutableRecordException('Payments cannot be deleted — use a reversal entry instead.');
        });
    }

    protected $fillable = [
        'user_id',
        'customer_id',
        'payment_no',
        'method',
        'status',
        'amount',
        'reference_number',
        'received_at',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function unallocatedAmount(): string
    {
        $allocated = $this->allocations()->sum('amount');

        return Money::subtract((string) $this->amount, (string) $allocated, 2);
    }
}
