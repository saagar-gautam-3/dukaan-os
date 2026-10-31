<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MovementType;
use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Database\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use BelongsToShop, HasFactory;

    /**
     * Stock movements are strictly append-only.
     */
    public const UPDATED_AT = null;

    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new ImmutableRecordException('Stock movements are strictly append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new ImmutableRecordException('Stock movements are strictly append-only and cannot be deleted.');
        });
    }

    protected $fillable = [
        'warehouse_id',
        'product_id',
        'movement_type',
        'quantity',
        'unit_cost',
        'reference_type',
        'reference_id',
        'notes',
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
            'movement_type' => MovementType::class,
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }
}
