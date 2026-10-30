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
    public static function formatLakhCrore(float|int|string|null $number, int $decimals = 2): string
    {
        if ($number === null || $number === '') {
            $number = '0';
        }

        if (is_string($number)) {
            $str = trim($number);
            $isNegative = str_starts_with($str, '-');
            $str = ltrim($str, '-+');

            if ($str === '' || ! is_numeric($str)) {
                $str = '0';
            }

            if ($decimals >= 0) {
                $parts = explode('.', $str);
                $intPart = ltrim($parts[0], '0') ?: '0';
                $rawDec = $parts[1] ?? '';

                if (strlen($rawDec) > $decimals) {
                    $roundDigit = (int) $rawDec[$decimals];
                    $trimmedDec = substr($rawDec, 0, $decimals);
                    if ($roundDigit >= 5) {
                        if ($decimals === 0) {
                            $intPart = function_exists('bcadd') ? bcadd($intPart, '1', 0) : (string) (((int) $intPart) + 1);
                            $decPart = null;
                        } else {
                            $combined = $intPart.'.'.$trimmedDec;
                            $addend = '0.'.str_repeat('0', $decimals - 1).'1';
                            $rounded = function_exists('bcadd') ? bcadd($combined, $addend, $decimals) : number_format((float) $str, $decimals, '.', '');
                            $rParts = explode('.', $rounded);
                            $intPart = $rParts[0];
                            $decPart = $rParts[1] ?? '';
                        }
                    } else {
                        $decPart = $decimals > 0 ? $trimmedDec : null;
                    }
                } else {
                    $decPart = $decimals > 0 ? str_pad($rawDec, $decimals, '0') : null;
                }
            } else {
                $intPart = $str;
                $decPart = null;
            }
        } else {
            $num = (float) $number;
            $isNegative = $num < 0;
            $absNum = abs($num);

            $formattedFloat = number_format($absNum, $decimals, '.', '');
            $parts = explode('.', $formattedFloat);
            $intPart = $parts[0];
            $decPart = $parts[1] ?? null;
        }

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
     * Format quantity (fractional units show 3 decimals, whole units 0) honouring Devanagari digit setting.
     *
     * @param float|int|string|null $quantity
     * @param bool|int $isFractional True for fractional (3 decimals), false for whole (0 decimals), or exact decimal count.
     * @param string|null $locale
     */
    public static function formatQuantity(float|int|string|null $quantity, bool|int $isFractional = false, ?string $locale = null): string
    {
        $decimals = is_bool($isFractional) ? ($isFractional ? 3 : 0) : $isFractional;

        if ($quantity === null || $quantity === '') {
            $quantity = '0';
        }

        if (is_string($quantity) && is_numeric($quantity)) {
            $str = trim($quantity);
            $isNegative = str_starts_with($str, '-');
            $str = ltrim($str, '-+');
            $parts = explode('.', $str);
            $intPart = ltrim($parts[0], '0') ?: '0';
            $rawDec = $parts[1] ?? '';

            if ($decimals > 0) {
                if (strlen($rawDec) > $decimals) {
                    $combined = $intPart.'.'.substr($rawDec, 0, $decimals);
                    $addend = '0.'.str_repeat('0', $decimals - 1).'1';
                    $roundDigit = (int) $rawDec[$decimals];
                    if ($roundDigit >= 5) {
                        $str = function_exists('bcadd') ? bcadd($combined, $addend, $decimals) : number_format((float) $str, $decimals, '.', '');
                    } else {
                        $str = $combined;
                    }
                    $parts = explode('.', $str);
                    $intPart = $parts[0];
                    $decPart = $parts[1] ?? '';
                } else {
                    $decPart = str_pad($rawDec, $decimals, '0');
                }
            } else {
                if (strlen($rawDec) > 0 && (int) $rawDec[0] >= 5) {
                    $intPart = function_exists('bcadd') ? bcadd($intPart, '1', 0) : (string) (((int) $intPart) + 1);
                }
                $decPart = null;
            }
            $formatted = $decPart !== null && $decimals > 0 ? "{$intPart}.{$decPart}" : $intPart;
            if ($isNegative && ($intPart !== '0' || ($decPart !== null && ltrim($decPart, '0') !== ''))) {
                $formatted = "-{$formatted}";
            }
        } else {
            $num = (float) $quantity;
            $formatted = number_format($num, $decimals, '.', '');
        }

        if (self::shouldDisplayDevanagari($locale)) {
            return self::toDevanagari($formatted);
        }

        return $formatted;
    }
}
