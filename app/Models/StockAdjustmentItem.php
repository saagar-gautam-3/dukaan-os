<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Database\Factories\StockAdjustmentItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockAdjustmentItem extends Model
{
    /** @use HasFactory<StockAdjustmentItemFactory> */
    use BelongsToShop, HasFactory;

    /**
     * Class A: append-only once created (items of a posted adjustment are
     * financial records and must not be changed; use a correcting adjustment).
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new ImmutableRecordException(__('errors.stock_adjustment_items_immutable'));
        });

        static::deleting(function (): never {
            throw new ImmutableRecordException(__('errors.stock_adjustment_items_cannot_delete'));
        });
    }

    protected $fillable = [
        'adjustment_id',
        'product_id',
        'type',
        'quantity',
        'unit_cost',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_cost' => 'decimal:2',
        ];
    }

    public function adjustment(): BelongsTo
    {
        return $this->belongsTo(StockAdjustment::class, 'adjustment_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
