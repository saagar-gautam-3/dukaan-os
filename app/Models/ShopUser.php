<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use Database\Factories\ShopUserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

class ShopUser extends Pivot
{
    /** @use HasFactory<ShopUserFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::updated(function (self $membership): void {
            if ($membership->wasChanged(['role_id', 'status'])) {
                $membership->user?->bumpPermissionsVersion();
            }
        });

        static::deleted(function (self $membership): void {
            $membership->user?->bumpPermissionsVersion();
        });
    }

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
        return $this->belongsTo(Role::class, 'role_id')->withoutGlobalScopes();
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $query = parent::resolveRouteBindingQuery($query, $value, $field);

        if (! TenantContext::has()) {
            return $query->whereRaw('0 = 1');
        }

        return $query->where($this->qualifyColumn('shop_id'), TenantContext::getShopId());
    }
}
