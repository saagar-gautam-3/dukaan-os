<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Exceptions\ImmutableRecordException;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only supplier ledger entries.
 * Balance (what we owe supplier) = SUM(debit) - SUM(credit).
 */
class SupplierLedgerEntry extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'supplier_id',
        'entry_type',
        'debit',
        'credit',
        'reference_type',
        'reference_id',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'entry_type' => LedgerEntryType::class,
        'debit' => 'decimal:2',
        'credit' => 'decimal:2',
    ];

    /**
     * Prevent any update or delete of posted ledger entries (append-only).
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new ImmutableRecordException(__('errors.supplier_ledger_immutable'));
        });

        static::deleting(function (): never {
            throw new ImmutableRecordException(__('errors.supplier_ledger_cannot_delete'));
        });
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeForReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }
}
