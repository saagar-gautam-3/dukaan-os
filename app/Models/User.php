<?php

declare(strict_types=1);

namespace App\Models;

use App\Tenancy\TenantContext;
use App\Traits\Auditable;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'phone', 'password', 'locale', 'is_platform_admin'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    /**
     * Attributes excluded from the generic Auditable trail.
     *
     * `permissions_version` is bumped internally whenever a membership changes
     * (role change, removal) and is not a user-facing data change, so it must
     * not create a second audit row next to the explicit staff_* entry.
     *
     * @var array<int, string>
     */
    protected array $auditExcluded = [
        'password',
        'remember_token',
        'two_factor_secret',
        'two_factor_recovery_codes',
        'updated_at',
        'permissions_version',
    ];

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
            'permissions_version' => 'integer',
            'is_platform_admin' => 'boolean',
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

    public function isPlatformAdmin(): bool
    {
        return (bool) $this->is_platform_admin;
    }

    public function bumpPermissionsVersion(): void
    {
        $this->increment('permissions_version');
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
