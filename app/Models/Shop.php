<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\Auditable;
use Database\Factories\ShopFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Shop extends Model
{
    /** @use HasFactory<ShopFactory> */
    use Auditable, HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'slug',
        'business_type',
        'timezone',
        'settings',
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
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'shop_user')
            ->using(ShopUser::class)
            ->withPivot(['id', 'role_id', 'status'])
            ->withTimestamps();
    }

    public function shopUsers(): HasMany
    {
        return $this->hasMany(ShopUser::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function currentSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }
}
