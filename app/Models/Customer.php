<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use App\Support\Money;
use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Customer extends Model
{
    use Auditable, BelongsToShop, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'phone',
        'email',
        'address',
        'pan_no',
        'credit_limit',
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(CustomerLedgerEntry::class);
    }

    public function reminderLogs(): HasMany
    {
        return $this->hasMany(ReminderLog::class);
    }

    /**
     * Ledger balance = SUM(debit) − SUM(credit).
     * Positive = customer owes money. Negative = customer has advance credit.
     */
    public function ledgerBalance(): string
    {
        $debit = $this->ledgerEntries()->sum('debit');
        $credit = $this->ledgerEntries()->sum('credit');

        return Money::subtract((string) $debit, (string) $credit, 2);
    }

    /**
     * Accessor for customer balance. Uses aggregated debit/credit if loaded, otherwise calls ledgerBalance().
     */
    public function getBalanceAttribute(): string
    {
        if (array_key_exists('total_debit', $this->attributes) || array_key_exists('total_credit', $this->attributes)) {
            return Money::subtract((string) ($this->attributes['total_debit'] ?? '0'), (string) ($this->attributes['total_credit'] ?? '0'), 2);
        }

        return $this->ledgerBalance();
    }

    /**
     * Outstanding open sales (posted, with due amount > 0).
     *
     * @return HasMany<Sale>
     */
    public function openSales(): HasMany
    {
        return $this->sales()
            ->where('status', SaleStatus::Posted->value)
            ->whereRaw('grand_total > paid_total')
            ->orderBy('posted_at');
    }

    /**
     * @deprecated Use ledgerBalance() instead; kept for backward compatibility.
     * Outstanding balance: sum of sale grand_totals (posted) - sum of verified payments.
     */
    public function outstandingBalance(): string
    {
        $totalSales = $this->sales()
            ->where('status', SaleStatus::Posted->value)
            ->sum('grand_total');

        $totalPaid = $this->payments()
            ->where('status', 'verified')
            ->sum('amount');

        return Money::subtract((string) $totalSales, (string) $totalPaid, 2);
    }

    /**
     * Check if adding a credit amount would exceed credit_limit.
     * Returns null if no limit set. Returns remaining headroom if within limit.
     */
    public function creditLimitHeadroom(): ?string
    {
        if (! $this->credit_limit || Money::compare((string) $this->credit_limit, '0', 2) <= 0) {
            return null;
        }

        $balance = $this->ledgerBalance();

        return Money::subtract((string) $this->credit_limit, $balance, 2);
    }

    /**
     * Whether the given additional credit amount exceeds the credit limit.
     */
    public function wouldExceedCreditLimit(string $additionalCredit): bool
    {
        if (! $this->credit_limit || Money::compare((string) $this->credit_limit, '0', 2) <= 0) {
            return false;
        }

        $newBalance = Money::add($this->ledgerBalance(), $additionalCredit, 2);

        return Money::compare($newBalance, (string) $this->credit_limit, 2) > 0;
    }
}
