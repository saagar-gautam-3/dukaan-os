<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Shop;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SuspendShop
{
    public function execute(Shop $shop, User $actor, string $reason): void
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => __('platform.reason_length'),
            ]);
        }

        if (! $shop->is_active || $shop->suspended_at !== null) {
            throw ValidationException::withMessages([
                'shop' => __('platform.already_suspended'),
            ]);
        }

        DB::transaction(function () use ($shop, $actor, $reason): void {
            $oldValues = [
                'is_active' => $shop->is_active,
                'suspended_at' => $shop->suspended_at,
                'suspended_by' => $shop->suspended_by,
                'suspended_reason' => $shop->suspended_reason,
            ];

            $shop->is_active = false;
            $shop->suspended_at = now();
            $shop->suspended_by = $actor->id;
            $shop->suspended_reason = $reason;
            $shop->saveQuietly();

            AuditTrail::record($shop, $actor->id, PlatformAuditEvents::SHOP_SUSPENDED, $oldValues, [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'reason' => $reason,
            ]);
        });
    }
}
