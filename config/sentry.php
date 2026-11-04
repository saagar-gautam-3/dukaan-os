<?php

declare(strict_types=1);

use App\Support\SentryEventScrubber;

return [
    'dsn' => env('SENTRY_LARAVEL_DSN', ''),
    'send_default_pii' => false,
    'before_send' => [SentryEventScrubber::class, 'scrub'],
    'ignore_transactions' => ['/up'],
    'breadcrumbs' => [
        'logs' => false,
        'cache' => false,
        'livewire' => false,
        'sql_queries' => false,
        'sql_bindings' => false,
        'queue_info' => false,
        'command_info' => false,
        'http_client_requests' => false,
        'notifications' => false,
    ],
];
