<?php

declare(strict_types=1);

namespace App\Support;

use App\Tenancy\TenantContext;

class NepaliNumber
{
    /**
     * Devanagari digits mapping.
     */
    public const DEVANAGARI_DIGITS = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    public const ASCII_DIGITS = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];

    /**
     * Format a numeric amount using South Asian (Lakh/Crore) grouping.
     * e.g. 100000 -> 1,00,000
     * Accepts decimal strings directly without float casting to preserve precision.
     */
    public static function formatLakhCrore(int|string|null $number, int $decimals = 2): string
    {
        $rounded = Money::round($number === null || $number === '' ? '0' : $number, max(0, $decimals));
        $isNegative = str_starts_with($rounded, '-');
        $unsigned = ltrim($rounded, '-');
        [$intPart, $decPart] = array_pad(explode('.', $unsigned, 2), 2, null);
        $intPart = ltrim($intPart, '0') ?: '0';

        if ($intPart === '0' && ($decPart === null || ltrim($decPart, '0') === '')) {
            $isNegative = false;
        }

        if (strlen($intPart) <= 3) {
            $grouped = $intPart;
        } else {
            $lastThree = substr($intPart, -3);
            $remaining = substr($intPart, 0, -3);
            $grouped = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $remaining).','.$lastThree;
        }

        $result = $decPart !== null && $decimals > 0 ? "{$grouped}.{$decPart}" : $grouped;

        return $isNegative ? "-{$result}" : $result;
    }

    /**
     * Convert ASCII digits in a string to Devanagari digits.
     */
    public static function toDevanagari(string|int $string): string
    {
        return str_replace(
            self::ASCII_DIGITS,
            self::DEVANAGARI_DIGITS,
            (string) $string
        );
    }

    /**
     * Convert Devanagari digits in a string to ASCII digits.
     */
    public static function toAscii(string $string): string
    {
        return str_replace(
            self::DEVANAGARI_DIGITS,
            self::ASCII_DIGITS,
            $string
        );
    }

    /**
     * Determine if Devanagari digits should be displayed based on per-shop setting or locale.
     */
    public static function shouldDisplayDevanagari(?string $locale = null): bool
    {
        if (TenantContext::has()) {
            $shop = TenantContext::currentShop();
            $setting = $shop->settings['devanagari_digits'] ?? null;
            if ($setting !== null) {
                return (bool) $setting;
            }
        }

        $activeLocale = $locale ?? app()->getLocale();

        return $activeLocale === 'ne';
    }

    /**
     * Format currency in NPR with symbol and proper grouping.
     * e.g. en: Rs. 1,25,000.00 | ne: रु. १,२५,०००.००
     */
    public static function formatNpr(int|string|null $amount, ?string $locale = null, bool $withSymbol = true): string
    {
        $activeLocale = $locale ?? app()->getLocale();
        $formatted = self::formatLakhCrore($amount ?? 0, 2);
        $useDevanagari = self::shouldDisplayDevanagari($activeLocale);

        if ($useDevanagari) {
            $devanagari = self::toDevanagari($formatted);
            $symbol = $activeLocale === 'en' ? 'Rs.' : 'रु.';

            return $withSymbol ? "{$symbol} {$devanagari}" : $devanagari;
        }

        $symbol = $activeLocale === 'ne' ? 'रु.' : 'Rs.';

        return $withSymbol ? "{$symbol} {$formatted}" : $formatted;
    }

    /**
     * Format quantity (fractional units show 3 decimals, whole units 0) honouring Devanagari digit setting.
     *
     * @param  bool|int  $isFractional  True for fractional (3 decimals), false for whole (0 decimals), or exact decimal count.
     */
    public static function formatQuantity(int|string|null $quantity, bool|int $isFractional = false, ?string $locale = null): string
    {
        $decimals = is_bool($isFractional) ? ($isFractional ? 3 : 0) : $isFractional;

        if ($quantity === null || $quantity === '') {
            $quantity = '0';
        }

        $formatted = Money::round($quantity, max(0, $decimals));

        if (self::shouldDisplayDevanagari($locale)) {
            return self::toDevanagari($formatted);
        }

        return $formatted;
    }
}
