<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\PlatformAuditEvents;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use PragmaRX\Google2FA\Google2FA;
use Tests\TestCase;

class PlatformResetTwoFactorCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_resets_platform_admin_2fa_audits_and_requires_confirmation(): void
    {
        $secret = (new Google2FA)->generateSecretKey();
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'email' => 'admin-reset@example.test',
        ]);
        $admin->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
            'two_factor_last_step' => 123,
        ])->save();

        $this->artisan('platform:reset-2fa', ['email' => 'admin-reset@example.test'])
            ->expectsConfirmation('Reset two-factor authentication for admin-reset@example.test?', 'yes')
            ->expectsOutputToContain('Two-factor authentication reset for admin-reset@example.test.')
            ->assertExitCode(0);

        $admin->refresh();
        $this->assertNull($admin->two_factor_secret);
        $this->assertNull($admin->two_factor_confirmed_at);
        $this->assertNull($admin->two_factor_recovery_codes);
        $this->assertNull($admin->two_factor_last_step);

        $log = AuditLog::query()->where('event', PlatformAuditEvents::TWO_FACTOR_RESET)->latest('id')->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame($admin->id, $log->auditable_id);
        $this->assertSame((new User)->getMorphClass(), $log->auditable_type);
        $this->assertSame('Reset by CLI', $log->new_values['note'] ?? null);
        $json = json_encode([$log->old_values, $log->new_values]);
        $this->assertStringNotContainsString($secret, (string) $json);
        $this->assertStringNotContainsString('ABCDE-12345', (string) $json);
    }

    public function test_command_cancels_without_reset_when_not_confirmed(): void
    {
        $admin = User::factory()->create([
            'is_platform_admin' => true,
            'email_verified_at' => now(),
            'email' => 'admin-cancel@example.test',
        ]);
        $admin->forceFill([
            'two_factor_secret' => (new Google2FA)->generateSecretKey(),
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => [Hash::make('ABCDE-12345')],
            'two_factor_last_step' => 1,
        ])->save();

        $this->artisan('platform:reset-2fa', ['email' => 'admin-cancel@example.test'])
            ->expectsConfirmation('Reset two-factor authentication for admin-cancel@example.test?', 'no')
            ->expectsOutputToContain('Operation cancelled.')
            ->assertExitCode(1);

        $admin->refresh();
        $this->assertNotNull($admin->two_factor_secret);
        $this->assertNotNull($admin->two_factor_confirmed_at);
        $this->assertDatabaseMissing('audit_logs', ['event' => PlatformAuditEvents::TWO_FACTOR_RESET]);
    }

    public function test_command_refuses_non_platform_admin_and_unknown_email(): void
    {
        $user = User::factory()->create([
            'is_platform_admin' => false,
            'email' => 'shop@example.test',
        ]);

        $this->artisan('platform:reset-2fa', ['email' => 'shop@example.test'])
            ->expectsOutputToContain('Platform admin account not found.')
            ->assertExitCode(1);

        $this->artisan('platform:reset-2fa', ['email' => 'missing@example.test'])
            ->expectsOutputToContain('Platform admin account not found.')
            ->assertExitCode(1);

        $this->assertDatabaseMissing('audit_logs', [
            'event' => PlatformAuditEvents::TWO_FACTOR_RESET,
            'user_id' => $user->id,
        ]);
    }
}
