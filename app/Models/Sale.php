<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sale extends Model
{
    use BelongsToShop, HasFactory;

    /**
     * Class C: once posted or cancelled the following core financial fields are
     * frozen. The only permitted mutable cache field is `paid_total` (updated
     * by RecordPayment to reflect running balance). Deleting a posted/cancelled
     * sale is also prohibited.
     */
    protected static function booted(): void
    {
        /** @var string[] Columns frozen after posting */
        $frozenColumns = [
            'invoice_no', 'customer_id', 'warehouse_id',
            'subtotal', 'discount_total', 'tax_total', 'grand_total',
            'posted_at', 'posted_by',
        ];

        static::updating(function (self $model) use ($frozenColumns): void {
            $originalStatus = SaleStatus::tryFrom((string) $model->getOriginal('status'));
            if ($originalStatus === null || ! $originalStatus->isImmutable()) {
                return;
            }

            foreach ($frozenColumns as $col) {
                if ($model->isDirty($col)) {
                    throw new ImmutableRecordException("Posted/cancelled sale field '{$col}' is frozen.");
                }
            }
        });

        static::deleting(function (self $model): void {
            if ($model->status !== null && $model->status->isImmutable()) {
                throw new ImmutableRecordException('Posted or cancelled sales cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'warehouse_id',
        'customer_id',
        'invoice_no',
        'status',
        'subtotal',
        'discount_total',
        'tax_total',
        'grand_total',
        'paid_total',
        'notes',
        'due_date',
        'posted_at',
        'posted_by',
    ];

    protected $casts = [
        'status' => SaleStatus::class,
        'subtotal' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'grand_total' => 'decimal:2',
        'paid_total' => 'decimal:2',
        'due_date' => 'date',
        'posted_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    public function returns(): HasMany
    {
        return $this->hasMany(SaleReturn::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isDraft(): bool
    {
        return $this->status === SaleStatus::Draft;
    }

    public function isPosted(): bool
    {
        return $this->status === SaleStatus::Posted;
    }

    public function dueAmount(): string
    {
        return bcsub((string) $this->grand_total, (string) $this->paid_total, 2);
    }
}
