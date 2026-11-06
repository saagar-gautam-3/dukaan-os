<?php

declare(strict_types=1);

namespace Tests\Unit\Testing;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class TestDatabaseGuardTest extends TestCase
{
    #[Test]
    public function guard_throws_for_non_test_database(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to run tests against non-test database demo');

        TestCase::assertTestDatabase('demo');
    }

    #[Test]
    public function guard_accepts_test_database(): void
    {
        TestCase::assertTestDatabase('dukaan_os_test');
        $this->assertTrue(true);
    }
}
