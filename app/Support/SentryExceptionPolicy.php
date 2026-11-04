<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class SentryExceptionPolicy
{
    public static function shouldReport(Throwable $exception): bool
    {
        if ($exception instanceof ValidationException
            || $exception instanceof AuthenticationException
            || $exception instanceof AuthorizationException) {
            return false;
        }

        return ! ($exception instanceof HttpExceptionInterface
            && in_array($exception->getStatusCode(), [403, 404], true));
    }
}
