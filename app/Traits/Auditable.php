<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\AuditLog;
use App\Tenancy\TenantContext;

trait Auditable
{
    /**
     * Boot the Auditable trait for a model.
     */
    public static function bootAuditable(): void
    {
        static::created(function ($model): void {
            $model->recordAuditLog('created', null, $model->getAuditableAttributes());
        });

        static::updated(function ($model): void {
            $dirty = $model->getDirty();
            // Ignore if only updated_at timestamp changed
            unset($dirty['updated_at']);

            if (empty($dirty)) {
                return;
            }

            $oldValues = [];
            $newValues = [];

            foreach (array_keys($dirty) as $attribute) {
                if ($model->isAuditExcluded($attribute)) {
                    continue;
                }
                $oldValues[$attribute] = $model->getOriginal($attribute);
                $newValues[$attribute] = $model->getAttribute($attribute);
            }

            if (! empty($newValues)) {
                $model->recordAuditLog('updated', $oldValues, $newValues);
            }
        });

        static::deleted(function ($model): void {
            $model->recordAuditLog('deleted', $model->getAuditableAttributes(), null);
        });
    }

    /**
     * Get auditable attributes excluding sensitive columns.
     *
     * @return array<string, mixed>
     */
    public function getAuditableAttributes(): array
    {
        $attributes = $this->attributesToArray();

        foreach ($this->getAuditExcludedAttributes() as $excluded) {
            unset($attributes[$excluded]);
        }

        return $attributes;
    }

    /**
     * Check if an attribute is excluded from auditing.
     */
    public function isAuditExcluded(string $attribute): bool
    {
        return in_array($attribute, $this->getAuditExcludedAttributes(), true);
    }

    /**
     * List of attributes excluded from audit logs.
     *
     * @return array<int, string>
     */
    public function getAuditExcludedAttributes(): array
    {
        return property_exists($this, 'auditExcluded')
            ? $this->auditExcluded
            : ['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'updated_at'];
    }

    /**
     * Persist an audit log record.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    protected function recordAuditLog(string $event, ?array $oldValues, ?array $newValues): void
    {
        $shopId = null;
        if (isset($this->shop_id)) {
            $shopId = $this->shop_id;
        } elseif (TenantContext::has()) {
            $shopId = TenantContext::getShopId();
        }

        AuditLog::create([
            'shop_id' => $shopId,
            'user_id' => auth()->id(),
            'event' => $event,
            'auditable_type' => $this->getMorphClass(),
            'auditable_id' => $this->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }
}
