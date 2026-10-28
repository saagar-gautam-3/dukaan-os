<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\ShopUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ShopUser extends Pivot
{
    /** @use HasFactory<ShopUserFactory> */
    use HasFactory;

    protected $table = 'shop_user';

    public $incrementing = true;

    protected $fillable = [
        'shop_id',
        'user_id',
        'role_id',
        'status',
    ];

    public function shop(): BelongsTo
    {
        return $this->belongsTo(Shop::class, 'shop_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }
}
