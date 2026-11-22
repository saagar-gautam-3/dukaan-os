<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Database\Factories\HeldSaleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeldSale extends Model
{
    /** @use HasFactory<HeldSaleFactory> */
    use Auditable, BelongsToShop, HasFactory;

    public const UPDATED_AT = null;

    protected $fillable = [
        'user_id',
        'customer_id',
        'label',
        'cart',
    ];

    protected function casts(): array
    {
        return [
            'cart' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
