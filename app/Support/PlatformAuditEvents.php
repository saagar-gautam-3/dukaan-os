<?php

declare(strict_types=1);

namespace App\Support;

final class PlatformAuditEvents
{
    public const SHOP_SUSPENDED = 'platform.shop_suspended';

    public const SHOP_REACTIVATED = 'platform.shop_reactivated';

    public const SHOP_VIEWED = 'platform.shop_viewed';

    public const TWO_FACTOR_ENABLED = 'platform.2fa_enabled';

    public const TWO_FACTOR_FAILED = 'platform.2fa_failed';

    public const TWO_FACTOR_RECOVERY_USED = 'platform.2fa_recovery_used';

    public const TWO_FACTOR_RECOVERY_REGENERATED = 'platform.2fa_recovery_regenerated';

    public const TWO_FACTOR_RESET = 'platform.2fa_reset';

    public const OWNER_RESET_LINK_CREATED = 'platform.owner_reset_link_created';

    /** @var list<string> */
    public const ALL = [
        self::SHOP_SUSPENDED,
        self::SHOP_REACTIVATED,
        self::SHOP_VIEWED,
        self::TWO_FACTOR_ENABLED,
        self::TWO_FACTOR_FAILED,
        self::TWO_FACTOR_RECOVERY_USED,
        self::TWO_FACTOR_RECOVERY_REGENERATED,
        self::TWO_FACTOR_RESET,
        self::OWNER_RESET_LINK_CREATED,
    ];

    /** @var list<string> */
    public const SHOP_HISTORY = [
        self::SHOP_SUSPENDED,
        self::SHOP_REACTIVATED,
    ];
}
