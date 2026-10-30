<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\AdjustmentStatus;
use App\Exceptions\ImmutableRecordException;
use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Database\Factories\StockAdjustmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockAdjustment extends Model
{
    /** @use HasFactory<StockAdjustmentFactory> */
    use Auditable, BelongsToShop, HasFactory;

    /**
     * Class C: once posted/adjusted, all fields are frozen (use a reversing
     * adjustment to correct; never mutate a financial record in-place).
     */
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            $originalStatus = AdjustmentStatus::tryFrom((string) $model->getOriginal('status'));
            if ($originalStatus !== null && $originalStatus !== AdjustmentStatus::Draft) {
                throw new ImmutableRecordException('Posted stock adjustments are immutable and cannot be modified.');
            }
        });

        static::deleting(function (self $model): void {
            if ($model->status !== AdjustmentStatus::Draft) {
                throw new ImmutableRecordException('Posted stock adjustments cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'warehouse_id',
        'adjustment_no',
        'reason',
        'status',
        'posted_at',
        'created_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => AdjustmentStatus::class,
            'posted_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class, 'adjustment_id');
    }
}
