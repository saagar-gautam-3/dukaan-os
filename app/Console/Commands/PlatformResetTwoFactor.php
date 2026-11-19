<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use App\Support\AuditTrail;
use App\Support\PlatformAuditEvents;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('platform:reset-2fa {email : The platform admin email address}')]
#[Description('Reset two-factor authentication for a platform administrator')]
class PlatformResetTwoFactor extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $user = User::query()->where('email', $email)->first();

        if (! $user || ! $user->isPlatformAdmin()) {
            $this->error('Platform admin account not found.');

            return self::FAILURE;
        }

        if (! $this->confirm("Reset two-factor authentication for {$user->email}?")) {
            $this->info('Operation cancelled.');

            return self::FAILURE;
        }

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        AuditTrail::record($user, $user->id, PlatformAuditEvents::TWO_FACTOR_RESET, null, [
            'note' => 'Reset by CLI',
            'reset' => true,
        ]);

        $this->info("Two-factor authentication reset for {$user->email}. The admin must set up 2FA at next platform login.");

        return self::SUCCESS;
    }
}
