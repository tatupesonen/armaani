<?php

namespace Tests\Feature\GameHandlers;

use App\Enums\GameType;
use App\GameHandlers\DayZHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class DayZHandlerTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    private DayZHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->handler = new DayZHandler;
    }

    public function test_game_type_returns_dayz(): void
    {
        $this->assertEquals(GameType::DayZ, $this->handler->gameType());
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

    public function test_boot_detection_string_is_null(): void
    {
        $this->assertSame([], $this->handler->getBootDetectionStrings());
    }

    public function test_does_not_support_headless_clients(): void
    {
        $this->assertFalse($this->handler->supportsHeadlessClients());
    }

    public function test_build_headless_client_command_returns_null(): void
    {
        $server = $this->createDayZServer();

        $this->assertNull($this->handler->buildHeadlessClientCommand($server, 0));
    }

    public function test_get_backup_file_path_returns_null(): void
    {
        $server = $this->createDayZServer();

        $this->assertNull($this->handler->getBackupFilePath($server));
    }

    public function test_get_backup_download_filename(): void
    {
        $server = $this->createDayZServer();

        $this->assertEquals('dayz_'.$server->id.'_backup', $this->handler->getBackupDownloadFilename($server));
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

    public function test_symlink_mods_throws_not_implemented(): void
    {
        $server = $this->createDayZServer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DayZ server support is not yet implemented.');

        $this->handler->symlinkMods($server);
    }

    public function test_symlink_missions_is_noop(): void
    {
        $server = $this->createDayZServer();

        // Should not throw
        $this->handler->symlinkMissions($server);
        $this->assertTrue(true);
    }

    public function test_copy_bikeys_throws_not_implemented(): void
    {
        $server = $this->createDayZServer();

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('DayZ server support is not yet implemented.');

        $this->handler->copyBiKeys($server);
    }
}
