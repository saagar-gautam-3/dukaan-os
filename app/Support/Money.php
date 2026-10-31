<?php

declare(strict_types=1);

namespace App\Support;

/** Decimal-string arithmetic for money and quantities stored in DECIMAL columns. */
final class Money
{
    public static function add(string|int $left, string|int $right, int $scale = 2): string
    {
        return bcadd((string) $left, (string) $right, $scale);
    }

    public static function subtract(string|int $left, string|int $right, int $scale = 2): string
    {
        return bcsub((string) $left, (string) $right, $scale);
    }

    public static function multiply(string|int $left, string|int $right, int $scale = 2): string
    {
        return bcmul((string) $left, (string) $right, $scale);
    }

    public static function divide(string|int $left, string|int $right, int $scale = 2): string
    {
        if (bccomp((string) $right, '0', max($scale, 14)) === 0) {
            throw new \DivisionByZeroError('Cannot divide a decimal value by zero.');
        }

        return bcdiv((string) $left, (string) $right, $scale);
    }

    public static function compare(string|int $left, string|int $right, int $scale = 2): int
    {
        return bccomp((string) $left, (string) $right, $scale);
    }

    /** Round half up and return a fixed scale decimal string. */
    public static function round(string|int $value, int $scale = 2): string
    {
        $value = trim((string) $value);
        if (! preg_match('/^[+-]?\d+(?:\.\d*)?$/', $value)) {
            throw new \InvalidArgumentException('Value must be a decimal string.');
        }

        $negative = str_starts_with($value, '-');
        $unsigned = ltrim($value, '+-');
        [$integer, $fraction] = array_pad(explode('.', $unsigned, 2), 2, '');
        $kept = substr($fraction, 0, $scale);
        $rounded = $integer.($scale > 0 ? '.'.str_pad($kept, $scale, '0') : '');

        if (isset($fraction[$scale]) && (int) $fraction[$scale] >= 5) {
            $rounded = bcadd($rounded, $scale > 0 ? '0.'.str_repeat('0', max(0, $scale - 1)).'1' : '1', $scale);
        }

        if ($negative && bccomp($rounded, '0', $scale) !== 0) {
            $rounded = '-'.$rounded;
        }

        return $rounded;
    }
}
