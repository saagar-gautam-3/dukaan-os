<?php

declare(strict_types=1);

namespace App\Support;

use Carbon\CarbonInterface;

class FiscalYear
{
    /**
     * Compute Nepal Fiscal Year based on Bikram Sambat calendar.
     * Nepal fiscal year begins on Shrawan 1 (BS month 4).
     *
     * Example:
     * - 2024-04-13 AD (BS 2081-01-01, Baisakh 1) -> "2080-81"
     * - 2024-07-16 AD (BS 2081-04-01, Shrawan 1) -> "2081-82"
     * - 2025-04-13 AD (BS 2082-01-01, Baisakh 1) -> "2081-82"
     *
     * @return string e.g. "2081-82"
     */
    public static function forDate(CarbonInterface|string|null $date = null): string
    {
        $bsDate = BikramSambat::toBs($date); // e.g. "2081-04-01"
        $parts = explode('-', $bsDate);

        $year = (int) ($parts[0] ?? 2081);
        $month = (int) ($parts[1] ?? 1);

        if ($month >= 4) {
            // Month 4 (Shrawan) to Month 12 (Chaitra)
            $nextYear = $year + 1;

            return sprintf('%d-%02d', $year, $nextYear % 100);
        }

        // Month 1 (Baisakh) to Month 3 (Ashadh) belongs to previous fiscal year
        $prevYear = $year - 1;

        return sprintf('%d-%02d', $prevYear, $year % 100);
    }

    /**
     * Get the current fiscal year in Nepal (Asia/Kathmandu timezone).
     */
    public static function current(): string
    {
        return self::forDate(now('Asia/Kathmandu'));
    }
}
