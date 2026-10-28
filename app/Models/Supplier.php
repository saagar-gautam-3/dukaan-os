<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Supplier extends Model
{
    /** @use HasFactory<SupplierFactory> */
    use Auditable, BelongsToShop, HasFactory;

    protected $fillable = [
        'name',
        'phone',
        'address',
        'pan_no',
        'is_active',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function purchases(): HasMany
    {
        return $this->hasMany(Purchase::class);
    }

    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(SupplierLedgerEntry::class);
    }

    /**
     * Supplier ledger balance = SUM(debit) − SUM(credit).
     * Positive = we owe the supplier. Negative = supplier owes us (overpayment).
     */
    public function ledgerBalance(): string
    {
        $debit = $this->ledgerEntries()->sum('debit');
        $credit = $this->ledgerEntries()->sum('credit');

        return bcsub((string) $debit, (string) $credit, 2);
    }
}
