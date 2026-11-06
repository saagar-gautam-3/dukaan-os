<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Guard against running the test suite against a non-test database.
     */
    public static function assertTestDatabase(string $database): void
    {
        if (! str_ends_with($database, '_test')) {
            throw new RuntimeException("Refusing to run tests against non-test database {$database}");
        }
    }

    protected function createApplication()
    {
        $app = parent::createApplication();

        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");

        self::assertTestDatabase($database);

        return $app;
    }
}
