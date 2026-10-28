<?php

declare(strict_types=1);

namespace App\Scopes;

use App\Tenancy\TenantContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class ShopScope implements Scope
{
    /**
     * Apply the scope to a given Eloquent query builder.
     */
    public function apply(Builder $builder, Model $model): void
    {
        if (TenantContext::has()) {
            $builder->where($model->qualifyColumn('shop_id'), TenantContext::getShopId());
        } else {
            // Fail closed: never return records when tenant context is absent
            $builder->whereRaw('0 = 1');
        }
    }
}
