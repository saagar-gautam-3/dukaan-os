<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public static function assertSafeTestEnvironment(string $env, string $database): void
    {
        if ($env !== 'testing' || $database !== 'dukaan_os_test') {
            throw new RuntimeException(
                "Refusing to run tests: APP_ENV=[{$env}], DB=[{$database}]. Open a fresh PowerShell, run Remove-Item Env:APP_ENV, then retry."
            );
        }
    }

    public static function assertTestDatabase(string $database): void
    {
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against non-test database {$database}");
        }
    }

    public function createApplication()
    {
        $app = parent::createApplication();

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        self::assertSafeTestEnvironment((string) config('app.env'), $database);

        return $app;
    }
}
