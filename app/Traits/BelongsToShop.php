<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\Shop;
use App\Scopes\ShopScope;
use App\Tenancy\Exceptions\MissingTenantContextException;
use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToShop
{
    /**
     * Boot the BelongsToShop trait for a model.
     */
    public static function bootBelongsToShop(): void
    {
        $scopeClass = config('tenancy.scope_class', ShopScope::class);
        static::addGlobalScope(new $scopeClass);

        static::creating(function ($model): void {
            if (TenantContext::has()) {
                // Always enforce tenant ID from TenantContext; never allow client input to override it
                $model->shop_id = TenantContext::getShopId();
            } elseif ($model->shop_id === null) {
                throw new MissingTenantContextException('Cannot create a tenant-owned record without an active TenantContext.');
            }
        });
    }

    /**
     * Initialize the trait and remove shop_id from fillable attributes.
     */
    public function initializeBelongsToShop(): void
    {
        $this->fillable = array_values(array_diff($this->fillable, ['shop_id']));
    }

    /**
     * Get the shop that owns the model.
     */
    public function shop(): BelongsTo
    {
        $tenantModel = config('tenancy.tenant_model', Shop::class);

        return $this->belongsTo($tenantModel, 'shop_id');
    }

    /**
     * Retrieve the model for a bound value with tenant isolation.
     *
     * @param  \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Relations\Relation  $query
     * @param  mixed  $value
     * @param  string|null  $field
     * @return \Illuminate\Database\Eloquent\Model|null
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $resolvedField = $field ?? $this->getRouteKeyName();

        $query = parent::resolveRouteBindingQuery($query, $value, $resolvedField);

        if (TenantContext::has()) {
            $query->where($this->qualifyColumn('shop_id'), TenantContext::getShopId());
        } else {
            $query->whereRaw('0 = 1');
        }

        return $query;
    }
}
