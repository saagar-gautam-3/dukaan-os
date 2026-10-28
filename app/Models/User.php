<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Tenancy\TenantContext;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function shops(): BelongsToMany
    {
        return $this->belongsToMany(Shop::class, 'shop_user')
            ->using(ShopUser::class)
            ->withPivot(['id', 'role_id', 'status'])
            ->withTimestamps();
    }

    public function shopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class);
    }

    public function activeShopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class)->where('status', 'active');
    }

    public function hasPermissionInCurrentShop(string $permission): bool
    {
        $membership = TenantContext::currentMembership();
        if ($membership === null || ! $membership->isActive()) {
            return false;
        }

        return $membership->role?->hasPermission($permission) ?? false;
    }
}
