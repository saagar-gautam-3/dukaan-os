<?php

declare(strict_types=1);

namespace App\Support;

use Sentry\Laravel\Facade as Sentry;
use Throwable;

class SentryExceptionReporter
{
    public function report(Throwable $exception): void
    {
        if (! filled(config('sentry.dsn')) || ! SentryExceptionPolicy::shouldReport($exception)) {
            return;
        }

        Sentry::captureException($exception);
    }
}
