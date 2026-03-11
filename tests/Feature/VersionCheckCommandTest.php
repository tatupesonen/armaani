<?php

namespace Tests\Feature;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class VersionCheckCommandTest extends TestCase
{
    public function test_displays_up_to_date_when_on_latest_version(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v'.config('app.version'),
            ]),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();
    }

    public function test_displays_warning_when_update_is_available(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v99.0.0',
            ]),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('new version')
            ->assertSuccessful();
    }

    public function test_handles_github_api_error_gracefully(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 500),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('Could not check for updates')
            ->assertSuccessful();
    }

    public function test_handles_no_releases_gracefully(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([], 404),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('Could not check for updates')
            ->assertSuccessful();
    }

    public function test_handles_network_error_gracefully(): void
    {
        Http::fake([
            'api.github.com/*' => function (): never {
                throw new ConnectionException('Connection timed out');
            },
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('Could not check for updates')
            ->assertSuccessful();
    }

    public function test_handles_missing_tag_name_gracefully(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'name' => 'Some Release',
            ]),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('Could not check for updates')
            ->assertSuccessful();
    }

    public function test_does_not_flag_update_when_on_newer_version(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'tag_name' => 'v0.0.1',
            ]),
        ]);

        $this->artisan('app:version-check')
            ->expectsOutputToContain('up to date')
            ->assertSuccessful();
    }
}
