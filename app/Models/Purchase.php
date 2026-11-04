<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use InvalidArgumentException;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use Auditable, BelongsToShop, HasFactory;

    protected $fillable = [
        'warehouse_id',
        'supplier_id',
        'purchase_no',
        'status',
        'subtotal',
        'tax_total',
        'discount_total',
        'grand_total',
        'notes',
        'purchase_date',
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
            'status' => PurchaseStatus::class,
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'grand_total' => 'decimal:2',
            'purchase_date' => 'date',
            'posted_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(function (Purchase $purchase): void {
            // Invariant: Posted purchases are immutable. Corrections must be made via return/reversal.
            $originalStatus = $purchase->getOriginal('status');
            $wasPosted = ($originalStatus instanceof PurchaseStatus && $originalStatus === PurchaseStatus::Posted)
                || $originalStatus === PurchaseStatus::Posted->value;

            if ($wasPosted) {
                throw new InvalidArgumentException(__('errors.posted_purchase_immutable'));
            }
        });

        static::deleting(function (Purchase $purchase): void {
            if ($purchase->status === PurchaseStatus::Posted) {
                throw new InvalidArgumentException(__('errors.posted_purchase_cannot_delete'));
            }
        });
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }

    public function isPosted(): bool
    {
        return $this->status === PurchaseStatus::Posted;
    }
}
