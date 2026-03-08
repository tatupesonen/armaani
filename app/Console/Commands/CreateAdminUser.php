<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class CreateAdminUser extends Command
{
    /** @var string */
    protected $signature = 'user:create-admin
                            {--name=Admin : The name of the admin user}
                            {--email=admin@armaani.local : The email address}
                            {--password= : The password (auto-generated if omitted)}';

    /** @var string */
    protected $description = 'Create an initial admin user if no users exist';

    public function handle(): int
    {
        if (User::count() > 0) {
            $this->info('Users already exist — skipping admin creation.');

            return self::SUCCESS;
        }

        $email = $this->option('email');
        $password = $this->option('password') ?: Str::random(16);
        $generated = ! $this->option('password');

        $user = User::create([
            'name' => $this->option('name'),
            'email' => $email,
            'password' => $password,
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        $this->info('');
        $this->info('╔══════════════════════════════════════════════╗');
        $this->info('║          ADMIN ACCOUNT CREATED               ║');
        $this->info('╠══════════════════════════════════════════════╣');
        $this->info("║  Email:    {$email}");
        $this->info("║  Password: {$password}");
        $this->info('╠══════════════════════════════════════════════╣');

        if ($generated) {
            $this->warn('║  This password will not be shown again.       ║');
            $this->warn('║  Change it after your first login.            ║');
        }

        $this->info('╚══════════════════════════════════════════════╝');
        $this->info('');

        return self::SUCCESS;
    }
}
