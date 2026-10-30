<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CashMovementType;
use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashMovement extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'cash_session_id',
        'type',
        'amount',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'type' => CashMovementType::class,
    ];

    protected static function booted(): void
    {
        static::updating(function (): void {
            throw new ImmutableRecordException('Cash movements are append-only and cannot be updated.');
        });

        static::deleting(function (): void {
            throw new ImmutableRecordException('Cash movements are append-only and cannot be deleted.');
        });
    }

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(CashSession::class, 'cash_session_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
