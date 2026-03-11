<?php

namespace Tests;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->shouldAuthenticate()) {
            $this->user = User::factory()->create();
            $this->actingAs($this->user);
        }
    }

    /**
     * Whether to auto-create and authenticate a user.
     * Override to return false in test classes that test unauthenticated behavior.
     */
    protected function shouldAuthenticate(): bool
    {
        return true;
    }

    /**
     * Switch to unauthenticated (guest) state for the current request.
     * Use in individual tests that need to verify auth guards.
     */
    protected function asGuest(): static
    {
        $this->app['auth']->forgetGuards();

        return $this;
    }
}
