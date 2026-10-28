<?php

declare(strict_types=1);

namespace App\Support;

class NepaliNumber
{
    /**
     * Devanagari digits mapping.
     */
    protected const DEVANAGARI_DIGITS = ['०', '१', '२', '३', '४', '५', '६', '७', '८', '९'];

    /**
     * Format a numeric amount using South Asian (Lakh/Crore) grouping.
     * e.g. 100000 -> 1,00,000
     */
    public static function formatLakhCrore(float|int|string $number, int $decimals = 2): string
    {
        $num = (float) $number;
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
    public static function toDevanagari(string $string): string
    {
        return str_replace(
            ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'],
            self::DEVANAGARI_DIGITS,
            $string
        );
    }

    /**
     * Format currency in NPR with symbol and proper grouping.
     * e.g. en: Rs. 1,25,000.00 | ne: रु. १,२५,०००.००
     */
    public static function formatNpr(float|int|string $amount, ?string $locale = null, bool $withSymbol = true): string
    {
        $activeLocale = $locale ?? app()->getLocale();
        $formatted = self::formatLakhCrore($amount, 2);

        if ($activeLocale === 'ne') {
            $devanagari = self::toDevanagari($formatted);

            return $withSymbol ? "रु. {$devanagari}" : $devanagari;
        }

        return $withSymbol ? "Rs. {$formatted}" : $formatted;
    }
}
