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
     */
    public static function formatLakhCrore(float|int|string|null $number, int $decimals = 2): string
    {
        $num = (float) ($number ?? 0);
        $isNegative = $num < 0;
        $absNum = abs($num);

        $formattedFloat = number_format($absNum, $decimals, '.', '');
        $parts = explode('.', $formattedFloat);
        $intPart = $parts[0];
        $decPart = $parts[1] ?? null;

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
    public static function toDevanagari(string|int|float $string): string
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
    public static function formatNpr(float|int|string|null $amount, ?string $locale = null, bool $withSymbol = true): string
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
     * Format quantity with optional decimals and Devanagari display.
     */
    public static function formatQuantity(float|int|string|null $quantity, int $decimals = 2, ?string $locale = null): string
    {
        $num = (float) ($quantity ?? 0);
        $formatted = rtrim(rtrim(number_format($num, $decimals, '.', ''), '0'), '.');
        if ($formatted === '') {
            $formatted = '0';
        }

        if (self::shouldDisplayDevanagari($locale)) {
            return self::toDevanagari($formatted);
        }

        return $formatted;
    }
}
