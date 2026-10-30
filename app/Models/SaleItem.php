<?php

declare(strict_types=1);

namespace App\Models;

use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SaleItem extends Model
{
    use BelongsToShop, HasFactory;

    /**
     * Class B: once the parent sale is posted or cancelled, its line items
     * become immutable financial records. Use a sale return to correct.
     */
    protected static function booted(): void
    {
        $guard = function (self $model): void {
            /** @var Sale|null $sale */
            $sale = $model->sale ?? Sale::withoutGlobalScopes()->find($model->sale_id);
            if ($sale && $sale->status !== null && $sale->status->isImmutable()) {
                throw new ImmutableRecordException('Sale items of a posted/cancelled sale cannot be modified or deleted.');
            }
        };

        static::updating($guard);
        static::deleting($guard);
    }

    protected $fillable = [
        'sale_id',
        'product_id',
        'product_variant_id',
        'quantity',
        'unit_price',
        'unit_cost',
        'discount',
        'tax',
        'line_total',
    ];

    protected $casts = [
        'quantity' => 'decimal:3',
        'unit_price' => 'decimal:2',
        'unit_cost' => 'decimal:2',
        'discount' => 'decimal:2',
        'tax' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function sale(): BelongsTo
    {
        return $this->belongsTo(Sale::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function productVariant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class);
    }

    public function saleReturnItems(): HasMany
    {
        return $this->hasMany(SaleReturnItem::class);
    }
}
