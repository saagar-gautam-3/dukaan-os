<?php

declare(strict_types=1);

namespace App\Actions\Platform;

use App\Models\Shop;
use App\Models\User;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Auth\Notifications\ResetPassword as ResetPasswordNotification;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

class CreateOwnerPasswordResetLink
{
    /**
     * @return array{url: string, owner: User, expires_at: Carbon}
     */
    public function execute(Shop $shop, User $actor, string $reason, bool $emailOwner = false): array
    {
        $reason = trim($reason);

        if (mb_strlen($reason) < 5 || mb_strlen($reason) > 500) {
            throw ValidationException::withMessages([
                'reason' => __('platform.reason_length'),
            ]);
        }

        $shop->loadMissing('ownerUser.user');
        $shopUser = $shop->ownerUser;
        $owner = $shopUser?->user;

        if ($owner === null) {
            throw ValidationException::withMessages([
                'shop' => __('platform.owner_not_found'),
            ]);
        }

        if ((bool) $owner->is_platform_admin) {
            throw ValidationException::withMessages([
                'shop' => __('platform.owner_is_platform_admin'),
            ]);
        }

        $expiryMinutes = (int) config('auth.passwords.users.expire', 60);

        $url = '';
        $expiresAt = now()->addMinutes($expiryMinutes);

        DB::transaction(function () use ($owner, $shop, $actor, $reason, $emailOwner, &$url, $expiryMinutes, &$expiresAt): void {
            $token = Password::broker()->createToken($owner);

            $url = route('password.reset', [
                'token' => $token,
                'email' => $owner->email,
            ]);

            if ($emailOwner) {
                $owner->notify(new ResetPasswordNotification($token));
            }

            $expiresAt = now()->addMinutes($expiryMinutes);

            AuditTrail::record($shop, $actor->id, PlatformAuditEvents::OWNER_RESET_LINK_CREATED, null, [
                'shop_id' => $shop->id,
                'shop_name' => $shop->name,
                'target_user_id' => $owner->id,
                'target_email' => $owner->email,
                'reason' => $reason,
            ]);
        });

        return [
            'url' => $url,
            'owner' => $owner,
            'expires_at' => $expiresAt,
        ];
    }
}
