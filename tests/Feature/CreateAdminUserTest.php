<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreateAdminUserTest extends TestCase
{
    protected function shouldAuthenticate(): bool
    {
        return false;
    }

    public function test_creates_admin_user_with_auto_generated_password(): void
    {
        $this->artisan('user:create-admin')
            ->expectsOutputToContain('ADMIN ACCOUNT CREATED')
            ->expectsOutputToContain('admin@armaani.local')
            ->expectsOutputToContain('This password will not be shown again.')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseHas('users', [
            'name' => 'Admin',
            'email' => 'admin@armaani.local',
        ]);

        $user = User::first();
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_creates_admin_user_with_custom_credentials(): void
    {
        $this->artisan('user:create-admin', [
            '--name' => 'John',
            '--email' => 'john@example.com',
            '--password' => 'my-secret-pass',
        ])
            ->expectsOutputToContain('john@example.com')
            ->expectsOutputToContain('my-secret-pass')
            ->assertSuccessful();

        $this->assertDatabaseHas('users', [
            'name' => 'John',
            'email' => 'john@example.com',
        ]);

        $user = User::first();
        $this->assertTrue(Hash::check('my-secret-pass', $user->password));
    }

    public function test_skips_creation_when_users_already_exist(): void
    {
        User::factory()->create();

        $this->artisan('user:create-admin')
            ->expectsOutputToContain('Users already exist')
            ->assertSuccessful();

        $this->assertDatabaseCount('users', 1);
    }

    public function test_does_not_show_password_warning_when_password_provided(): void
    {
        $this->artisan('user:create-admin', [
            '--password' => 'explicit-pass',
        ])
            ->expectsOutputToContain('ADMIN ACCOUNT CREATED')
            ->doesntExpectOutputToContain('This password will not be shown again.')
            ->assertSuccessful();
    }
}
