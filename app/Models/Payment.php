<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Traits\BelongsToShop;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    use BelongsToShop, HasFactory;

    protected $fillable = [
        'user_id',
        'customer_id',
        'payment_no',
        'method',
        'status',
        'amount',
        'reference_number',
        'received_at',
        'notes',
        'idempotency_key',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'status' => PaymentStatus::class,
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function unallocatedAmount(): string
    {
        $allocated = $this->allocations()->sum('amount');

        return bcsub((string) $this->amount, (string) $allocated, 2);
    }
}
