<?php

declare(strict_types=1);

namespace App\Tenancy\Exceptions;

use RuntimeException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

class MissingTenantContextException extends RuntimeException implements HttpExceptionInterface
{
    public function getStatusCode(): int
    {
        return 403;
    }

    public function getHeaders(): array
    {
        return [];
    }
}
