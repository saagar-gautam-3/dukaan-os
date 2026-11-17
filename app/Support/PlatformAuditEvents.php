<?php

declare(strict_types=1);

namespace App\Support;

final class PlatformAuditEvents
{
    public const SHOP_SUSPENDED = 'platform.shop_suspended';

    public const SHOP_REACTIVATED = 'platform.shop_reactivated';

    public const SHOP_VIEWED = 'platform.shop_viewed';

    /** @var list<string> */
    public const ALL = [
        self::SHOP_SUSPENDED,
        self::SHOP_REACTIVATED,
        self::SHOP_VIEWED,
    ];

    /** @var list<string> */
    public const SHOP_HISTORY = [
        self::SHOP_SUSPENDED,
        self::SHOP_REACTIVATED,
    ];
}
