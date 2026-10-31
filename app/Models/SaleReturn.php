<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReturnStatus;
use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleReturn extends Model
{
    use BelongsToShop, HasFactory;

    /**
     * Class A: sale returns are append-only financial records. Once processed,
     * they cannot be modified or removed — issue a new sale to re-sell returned
     * goods instead.
     */
    protected static function booted(): void
    {
        static::updating(function (self $model): void {
            $original = ReturnStatus::tryFrom((string) $model->getRawOriginal('status'));
            if ($original !== null && $original !== ReturnStatus::Pending) {
                throw new ImmutableRecordException('Completed or cancelled sale returns are immutable and cannot be modified.');
            }
        });

        static::deleting(function (self $model): void {
            if ($model->status !== ReturnStatus::Pending) {
                throw new ImmutableRecordException('Completed or cancelled sale returns cannot be deleted.');
            }
        });
    }

    protected $fillable = [
        'sale_id',
        'return_no',
        'status',
        'total',
        'reason',
        'processed_at',
        'processed_by',
    ];

    protected $casts = [
        'status' => ReturnStatus::class,
        'total' => 'decimal:2',
        'processed_at' => 'datetime',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }
}
