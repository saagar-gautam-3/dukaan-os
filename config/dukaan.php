<?php

declare(strict_types=1);

/**
 * Dukaan OS — Application Configuration
 *
 * Central place for app-wide constants that don't belong to a single module.
 * Module-specific config lives inside each module's own config file.
 *
 * All values are overridable via the environment; use env() with sensible defaults.
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Application Identity
    |--------------------------------------------------------------------------
    */
    'name' => env('APP_NAME', 'Dukaan OS'),
    'version' => '0.1.0',

    /*
    |--------------------------------------------------------------------------
    | Locale & Currency
    |--------------------------------------------------------------------------
    | Default locale used when none is set per-shop.
    | currency_code : ISO 4217 (NPR = Nepalese Rupee)
    | currency_symbol_en : English symbol (Rs.)
    | currency_symbol_ne : Nepali Unicode symbol (रु.)
    | Grouping style for NPR: lakh/crore (not million/billion).
    */
    'locale' => env('APP_LOCALE', 'ne'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'currency_code' => 'NPR',
    'currency_symbol_en' => 'Rs.',
    'currency_symbol_ne' => 'रु.',
    'currency_decimals' => 2,

    /*
    |--------------------------------------------------------------------------
    | Timezone
    |--------------------------------------------------------------------------
    | All timestamps stored in UTC; display layer converts to Asia/Kathmandu.
    */
    'timezone' => env('APP_TIMEZONE', 'Asia/Kathmandu'),

    /*
    |--------------------------------------------------------------------------
    | Fiscal Year
    |--------------------------------------------------------------------------
    | Nepal fiscal year starts 1 Shrawan (mid-July). The offset lets us compute
    | invoice sequence resets and year-over-year reports.
    | fiscal_year_start_month: Gregorian month number when the FY starts (7 = July).
    | fiscal_year_start_day  : Day of month (typically 16 for mid-July).
    */
    'fiscal_year_start_month' => 7,
    'fiscal_year_start_day' => 16,

    /*
    |--------------------------------------------------------------------------
    | Invoice & Sequence
    |--------------------------------------------------------------------------
    | invoice_prefix: e.g. "INV-" → "INV-2081-00001"
    | Sequences are per-shop, reset each fiscal year.
    */
    'invoice_prefix' => env('INVOICE_PREFIX', 'INV-'),

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    */
    'pagination' => [
        'default' => 25,
        'max' => 100,
    ],

    /*
    |--------------------------------------------------------------------------
    | Queue
    |--------------------------------------------------------------------------
    | reminder_queue: dedicated queue for Udhar reminder jobs.
    | report_queue  : dedicated queue for heavy report generation.
    */
    'queues' => [
        'default' => env('QUEUE_CONNECTION', 'database'),
        'reminders' => 'reminders',
        'reports' => 'reports',
    ],

    /*
    |--------------------------------------------------------------------------
    | Feature Flags (disabled until the matching step is complete)
    |--------------------------------------------------------------------------
    */
    'features' => [
        'qr_payments' => env('FEATURE_QR_PAYMENTS', false),
        'staff_attendance' => env('FEATURE_STAFF_ATTENDANCE', false),
        'saas_admin_panel' => env('FEATURE_SAAS_ADMIN', false),
    ],

];
