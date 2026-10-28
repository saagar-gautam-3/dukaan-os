<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LedgerEntryType;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Append-only customer ledger entries.
 * Never soft-delete or update a posted row — use reversal entries.
 * Balance = SUM(debit) - SUM(credit).
 */
class CustomerLedgerEntry extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'customer_id',
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
     * Prevent any update to a posted ledger entry (append-only).
     */
    protected static function booted(): void
    {
        static::updating(function (): never {
            throw new \LogicException('Ledger entries are append-only and cannot be modified.');
        });

        static::deleting(function (): never {
            throw new \LogicException('Ledger entries are append-only and cannot be deleted.');
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Scope: entries for a specific reference (e.g. sale_id). */
    public function scopeForReference(Builder $query, string $type, int $id): Builder
    {
        return $query->where('reference_type', $type)->where('reference_id', $id);
    }

    /** Scope: debit entries only. */
    public function scopeDebits(Builder $query): Builder
    {
        return $query->where('debit', '>', 0);
    }

    /** Scope: credit entries only. */
    public function scopeCredits(Builder $query): Builder
    {
        return $query->where('credit', '>', 0);
    }
}
