<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Shop;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Support\Facades\DB;

class ReactivateShop
{
    public function execute(Shop $shop, User $actor): void
    {
        DB::transaction(function () use ($shop, $actor): void {
            $oldValues = [
                'is_active' => $shop->is_active,
                'suspended_at' => $shop->suspended_at,
                'suspended_by' => $shop->suspended_by,
                'suspended_reason' => $shop->suspended_reason,
            ];

            $shop->is_active = true;
            $shop->suspended_at = null;
            $shop->suspended_by = null;
            $shop->suspended_reason = null;
            $shop->saveQuietly();

            AuditTrail::record($shop, $actor->id, PlatformAuditEvents::SHOP_REACTIVATED, $oldValues, [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'reason' => null,
            ]);
        });
    }
}
