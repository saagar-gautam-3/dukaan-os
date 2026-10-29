<?php

declare(strict_types=1);

use App\Support\BikramSambat;
use App\Support\FiscalYear;
use App\Support\NepaliNumber;
use App\Support\NepaliPhone;
use Carbon\CarbonInterface;

if (! function_exists('format_npr')) {
    function format_npr(float|int|string|null $amount, ?string $locale = null, bool $withSymbol = true): string
    {
        return NepaliNumber::formatNpr($amount, $locale, $withSymbol);
    }
}

if (! function_exists('to_devanagari')) {
    function to_devanagari(string|int|float $value): string
    {
        return NepaliNumber::toDevanagari($value);
    }
}

if (! function_exists('to_ascii_digits')) {
    function to_ascii_digits(string $value): string
    {
        return NepaliNumber::toAscii($value);
    }
}

if (! function_exists('bs_date')) {
    function bs_date(CarbonInterface|string|null $date = null, ?string $locale = null, bool $withAd = false): string
    {
        return BikramSambat::formatBs($date, $locale, $withAd);
    }
}

if (! function_exists('nepali_phone')) {
    function nepali_phone(?string $phone): ?string
    {
        return NepaliPhone::normalize($phone);
    }
}

if (! function_exists('nepal_fiscal_year')) {
    function nepal_fiscal_year(CarbonInterface|string|null $date = null): string
    {
        return FiscalYear::forDate($date);
    }
}
