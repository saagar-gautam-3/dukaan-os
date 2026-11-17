<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\Test;
use RuntimeException;
use Tests\TestCase;

class TestEnvironmentGuardTest extends TestCase
{
    #[Test]
    public function passes_for_testing_and_dukaan_os_test(): void
    {
        TestCase::assertSafeTestEnvironment('testing', 'dukaan_os_test');
        $this->assertTrue(true);
    }

    #[Test]
    public function throws_for_demo(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests: APP_ENV=[demo], DB=[dukaan_os_demo]. Open a fresh PowerShell, run Remove-Item Env:APP_ENV, then retry.');

        TestCase::assertSafeTestEnvironment('demo', 'dukaan_os_demo');
    }

    #[Test]
    public function throws_for_local(): void
    {
        $this->expectException(RuntimeException::class);

        TestCase::assertSafeTestEnvironment('local', 'dukaan_os_test');
    }

    #[Test]
    public function throws_for_production(): void
    {
        $this->expectException(RuntimeException::class);

        TestCase::assertSafeTestEnvironment('production', 'dukaan_os_test');
    }

    #[Test]
    public function throws_for_empty(): void
    {
        $this->expectException(RuntimeException::class);

        TestCase::assertSafeTestEnvironment('', 'dukaan_os_test');
    }

    #[Test]
    public function throws_for_testing_with_dukaan_os(): void
    {
        $this->expectException(RuntimeException::class);

        TestCase::assertSafeTestEnvironment('testing', 'dukaan_os');
    }

    #[Test]
    public function throws_for_testing_with_dukaan_os_demo(): void
    {
        $this->expectException(RuntimeException::class);

        TestCase::assertSafeTestEnvironment('testing', 'dukaan_os_demo');
    }
}
