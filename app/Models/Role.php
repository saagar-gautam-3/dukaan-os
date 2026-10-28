<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use App\Traits\BelongsToShop;
use Database\Factories\RoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Role extends Model
{
    /** @use HasFactory<RoleFactory> */
    use Auditable, BelongsToShop, HasFactory;

    protected $fillable = [
        'name',
        'guard_name',
    ];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    public function shopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class, 'role_id');
    }

    public function hasPermission(string $permissionName): bool
    {
        return $this->permissions->contains('name', $permissionName);
    }
}
