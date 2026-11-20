<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
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

    protected function actingAsPlatformAdmin(?User $user = null, array $attributes = []): User
    {
        $google2fa = new Google2FA;

        if ($user === null) {
            $user = User::factory()->create(array_merge([
                'is_platform_admin' => true,
                'email_verified_at' => now(),
            ], $attributes));
        } else {
            $user->forceFill(array_merge([
                'is_platform_admin' => true,
                'email_verified_at' => $user->email_verified_at ?? now(),
            ], $attributes));
        }

        if (! $user->hasPlatformTwoFactorConfirmed()) {
            $user->forceFill([
                'two_factor_secret' => $google2fa->generateSecretKey(),
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => [Hash::make('RECOVERY-ONE1')],
                'two_factor_last_step' => null,
            ]);
        }

        $user->save();

        $this->actingAs($user);
        $this->withSession(['platform_2fa_passed_at' => now()->timestamp]);

        return $user;
    }
}
