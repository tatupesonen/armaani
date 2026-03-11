<?php

namespace Tests\Feature\GameHandlers;

use App\GameHandlers\DayZHandler;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class DayZHandlerTest extends TestCase
{
    use CreatesGameScenarios;

    private DayZHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new DayZHandler;
    }

    public function test_game_type_returns_dayz(): void
    {
        $this->assertEquals('dayz', $this->handler->value());
    }

    public function test_get_binary_path_returns_dayz_server(): void
    {
        $server = $this->createDayZServer();

        $expected = $server->gameInstall->getInstallationPath().'/DayZServer_x64';
        $this->assertEquals($expected, $this->handler->getBinaryPath($server));
    }

    public function test_get_profile_name_returns_dayz_prefix(): void
    {
        $server = $this->createDayZServer();

        $this->assertEquals('dayz_'.$server->id, $this->handler->getProfileName($server));
    }

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->createDayZServer();

        $expected = $server->getProfilesPath().'/server.log';
        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    public function test_build_launch_command_throws_not_implemented(): void
    {
        $server = $this->createDayZServer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DayZ server support is not yet implemented.');

        $this->handler->buildLaunchCommand($server);
    }

    public function test_generate_config_files_throws_not_implemented(): void
    {
        $server = $this->createDayZServer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DayZ server support is not yet implemented.');

        $this->handler->generateConfigFiles($server);
    }

    public function test_create_related_settings_creates_dayz_settings(): void
    {
        $server = $this->createDayZServer();

        // Delete existing settings created by factory
        $server->dayzSettings()->delete();

        $this->handler->createRelatedSettings($server);

        $this->assertNotNull($server->fresh()->dayzSettings);
    }

    public function test_server_validation_rules_returns_empty_array(): void
    {
        $this->assertSame([], $this->handler->serverValidationRules());
    }

    public function test_settings_validation_rules_returns_empty_array(): void
    {
        $this->assertSame([], $this->handler->settingsValidationRules());
    }
}
