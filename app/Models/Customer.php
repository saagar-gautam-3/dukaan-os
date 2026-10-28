<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\SaleStatus;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use BelongsToShop, HasFactory;

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

    /**
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

        return bcsub((string) $totalSales, (string) $totalPaid, 2);
    }
}
