<?php

declare(strict_types=1);

namespace App\Support;

use Anuzpandey\LaravelNepaliDate\LaravelNepaliDate;
use App\Models\Shop;
use App\Tenancy\TenantContext;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Throwable;

class BikramSambat
{
    /**
     * Convert Gregorian AD date to Bikram Sambat (BS).
     * Returns string formatted as Y-m-d (e.g. 2081-01-01) or custom format.
     */
    public static function toBs(CarbonInterface|string|null $date = null, string $format = 'Y-m-d'): string
    {
        if ($date === null) {
            $date = now('Asia/Kathmandu');
        }

        if ($date instanceof CarbonInterface) {
            $adString = $date->toDateString();
        } else {
            $adString = Carbon::parse($date)->toDateString();
        }

        try {
            return LaravelNepaliDate::from($adString)->toNepaliDate($format);
        } catch (Throwable) {
            return $adString;
        }
    }

    /**
     * Convert Bikram Sambat (BS) date to Gregorian AD date.
     * Returns string formatted as Y-m-d (e.g. 2024-04-13).
     */
    public static function toAd(string $bsDate, string $format = 'Y-m-d'): string
    {
        try {
            return LaravelNepaliDate::from($bsDate)->toEnglishDate($format);
        } catch (Throwable) {
            return $bsDate;
        }
    }

    /**
     * Check whether BS dates should be shown for the current/given shop.
     */
    public static function shouldShowBs(?Shop $shop = null): bool
    {
        $currentShop = $shop ?? (TenantContext::has() ? TenantContext::currentShop() : null);
        if (! $currentShop) {
            return false;
        }

        return (bool) ($currentShop->settings['show_bs_dates'] ?? false);
    }

    /**
     * Format a date for display, honoring per-shop "show BS dates" setting.
     * If show_bs_dates is enabled: returns BS date (or BS with AD).
     */
    public static function formatBs(CarbonInterface|string|null $date, ?string $locale = null, bool $withAd = false): string
    {
        if ($date === null) {
            return '';
        }

        $carbon = $date instanceof CarbonInterface ? $date : Carbon::parse($date);
        $adFormatted = $carbon->toDateString();
        $bsDate = self::toBs($carbon);

        $activeLocale = $locale ?? app()->getLocale();
        if (NepaliNumber::shouldDisplayDevanagari($activeLocale)) {
            $bsDate = NepaliNumber::toDevanagari($bsDate);
        }

        if ($withAd) {
            return "{$bsDate} BS ({$adFormatted} AD)";
        }

        return $bsDate;
    }
}
