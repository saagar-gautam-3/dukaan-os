<?php

declare(strict_types=1);

namespace App\Support;

class NepaliPhone
{
    /**
     * Normalise Nepal phone number:
     * - Convert Devanagari numerals to ASCII digits
     * - Strip non-digits (spaces, dashes, parens, +)
     * - Strip leading country code (+977 or 977)
     * - Strip leading 0
     * - Returns consistent 10-digit form for mobile (e.g. 98XXXXXXXX) or cleaned string.
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $cleaned = trim($phone);
        if ($cleaned === '') {
            return null;
        }

        // Convert Devanagari digits to ASCII
        $cleaned = NepaliNumber::toAscii($cleaned);

        // Strip everything except digits
        $cleaned = (string) preg_replace('/\D/', '', $cleaned);

        // If prefixed with 977 (country code) and total length >= 12 (e.g. 97798XXXXXXXX -> 98XXXXXXXX)
        if (str_starts_with($cleaned, '977') && strlen($cleaned) >= 12) {
            $cleaned = substr($cleaned, 3);
        }

        // If prefixed with 0 and length is 11 (e.g. 098XXXXXXXX -> 98XXXXXXXX)
        if (str_starts_with($cleaned, '0') && strlen($cleaned) === 11) {
            $cleaned = substr($cleaned, 1);
        }

        return $cleaned !== '' ? $cleaned : null;
    }

    /**
     * Validate whether the normalised number is a valid 10-digit mobile number starting with 9.
     */
    public static function isValidMobile(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return false;
        }

        return (bool) preg_match('/^9\d{9}$/', $normalized);
    }

    /**
     * Format a 10-digit phone number for display (e.g. 9841-234567).
     */
    public static function format(?string $phone): string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return '';
        }

        if (strlen($normalized) === 10) {
            return substr($normalized, 0, 4).'-'.substr($normalized, 4);
        }

        return $normalized;
    }
}

