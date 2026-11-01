<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class PlatformCreateAdmin extends Command
{
    protected $signature = 'platform:create-admin {--email=} {--name=}';

    protected $description = 'Create a platform administrator account';

    public function handle(): int
    {
        $name = $this->option('name') ?: $this->ask('Name');
        $email = $this->option('email') ?: $this->ask('Email');
        $password = $this->secret('Password');

        if (! $name || ! $email || ! $password) {
            $this->error('Name, email and password are required.');

            return self::FAILURE;
        }

        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => Hash::make($password),
            'is_platform_admin' => true,
        ]);

        $this->info("Platform admin created: {$user->email}");

        return self::SUCCESS;
    }
}
