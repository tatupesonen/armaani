<?php

namespace Tests\Feature\GameHandlers;

use App\Enums\GameType;
use App\GameHandlers\ReforgerHandler;
use App\Models\ModPreset;
use App\Models\ReforgerMod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class ReforgerHandlerTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    private ReforgerHandler $handler;

    private string $testServersBasePath;

    private string $testGamesBasePath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testServersBasePath = sys_get_temp_dir().'/armaman_test_servers_'.uniqid();
        $this->testGamesBasePath = sys_get_temp_dir().'/armaman_test_games_'.uniqid();

        config([
            'arma.servers_base_path' => $this->testServersBasePath,
            'arma.games_base_path' => $this->testGamesBasePath,
        ]);

        $this->handler = new ReforgerHandler;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testServersBasePath);
        File::deleteDirectory($this->testGamesBasePath);

        parent::tearDown();
    }

    public function test_game_type_returns_arma_reforger(): void
    {
        $this->assertEquals(GameType::ArmaReforger, $this->handler->gameType());
    }

    public function test_get_binary_path_returns_arma_reforger_server(): void
    {
        $server = $this->createReforgerServer();

        $expected = $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
        $this->assertEquals($expected, $this->handler->getBinaryPath($server));
    }

    public function test_get_profile_name_returns_reforger_prefix(): void
    {
        $server = $this->createReforgerServer();

        $this->assertEquals('reforger_'.$server->id, $this->handler->getProfileName($server));
    }

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->createReforgerServer();

        $expected = $server->getProfilesPath().'/server.log';
        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    public function test_boot_detection_string_is_null(): void
    {
        $this->assertNull($this->handler->getBootDetectionString());
    }

    public function test_does_not_support_headless_clients(): void
    {
        $this->assertFalse($this->handler->supportsHeadlessClients());
    }

    public function test_build_headless_client_command_returns_null(): void
    {
        $server = $this->createReforgerServer();

        $this->assertNull($this->handler->buildHeadlessClientCommand($server, 0));
    }

    public function test_get_backup_file_path_returns_null(): void
    {
        $server = $this->createReforgerServer();

        $this->assertNull($this->handler->getBackupFilePath($server));
    }

    public function test_get_backup_download_filename(): void
    {
        $server = $this->createReforgerServer();

        $this->assertEquals('reforger_'.$server->id.'_backup', $this->handler->getBackupDownloadFilename($server));
    }

    public function test_build_launch_command_includes_config_and_flags(): void
    {
        $server = $this->createReforgerServer();

        $command = $this->handler->buildLaunchCommand($server);

        $expectedBinary = $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
        $this->assertStringStartsWith($expectedBinary, $command);
        $this->assertStringContainsString('-config '.$server->getProfilesPath().'/REFORGER_'.$server->id.'.json', $command);
        $this->assertStringContainsString('-maxFPS 60', $command);
        $this->assertStringContainsString('-backendlog', $command);
        $this->assertStringContainsString('-logAppend', $command);
    }

    public function test_build_launch_command_includes_additional_params(): void
    {
        $server = $this->createReforgerServer(['additional_params' => '-logStats 10000']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertStringContainsString('-logStats 10000', $command);
    }

    public function test_generate_config_files_writes_json_config(): void
    {
        $server = $this->createReforgerServer([
            'name' => 'My Reforger Server',
            'password' => 'secret',
            'admin_password' => 'admin123',
            'max_players' => 64,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $this->assertFileExists($configPath);

        $config = json_decode(file_get_contents($configPath), true);
        $this->assertEquals('0.0.0.0', $config['bindAddress']);
        $this->assertEquals($server->port, $config['bindPort']);
        $this->assertEquals($server->query_port, $config['a2s']['port']);
        $this->assertEquals('My Reforger Server', $config['game']['name']);
        $this->assertEquals('secret', $config['game']['password']);
        $this->assertEquals('admin123', $config['game']['passwordAdmin']);
        $this->assertEquals(64, $config['game']['maxPlayers']);
        $this->assertEquals('{ECC61978EDCC2B5A}Missions/23_Campaign.conf', $config['game']['scenarioId']);
        $this->assertTrue($config['game']['gameProperties']['thirdPersonViewEnabled']);
    }

    public function test_generate_config_files_includes_reforger_mods(): void
    {
        $server = $this->createReforgerServer();

        $mod1 = ReforgerMod::factory()->create(['mod_id' => 'AAAA1111BBBB2222', 'name' => 'Test Mod 1']);
        $mod2 = ReforgerMod::factory()->create(['mod_id' => 'CCCC3333DDDD4444', 'name' => 'Test Mod 2']);

        $preset = ModPreset::factory()->create(['game_type' => GameType::ArmaReforger]);
        $preset->reforgerMods()->attach([$mod1->id, $mod2->id]);

        $server->update(['active_preset_id' => $preset->id]);
        $server->load('activePreset.reforgerMods');

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $config = json_decode(file_get_contents($configPath), true);

        $this->assertCount(2, $config['game']['mods']);
        $this->assertEquals('AAAA1111BBBB2222', $config['game']['mods'][0]['modId']);
        $this->assertEquals('Test Mod 1', $config['game']['mods'][0]['name']);
        $this->assertEquals('CCCC3333DDDD4444', $config['game']['mods'][1]['modId']);
        $this->assertEquals('Test Mod 2', $config['game']['mods'][1]['name']);
    }

    public function test_generate_config_files_empty_mods_when_no_preset(): void
    {
        $server = $this->createReforgerServer();
        $server->update(['active_preset_id' => null]);
        $server->refresh();

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/REFORGER_'.$server->id.'.json';
        $config = json_decode(file_get_contents($configPath), true);

        $this->assertEmpty($config['game']['mods']);
    }

    public function test_symlink_mods_is_noop(): void
    {
        $server = $this->createReforgerServer();

        // Should not throw or create any symlinks
        $this->handler->symlinkMods($server);

        $gameInstallPath = $server->gameInstall->getInstallationPath();
        $this->assertDirectoryDoesNotExist($gameInstallPath.'/@');
    }

    public function test_symlink_missions_is_noop(): void
    {
        $server = $this->createReforgerServer();

        $this->handler->symlinkMissions($server);

        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';
        $this->assertDirectoryDoesNotExist($mpmissionsPath);
    }

    public function test_copy_bikeys_is_noop(): void
    {
        $server = $this->createReforgerServer();

        $this->handler->copyBiKeys($server);

        $keysPath = $server->gameInstall->getInstallationPath().'/keys';
        $this->assertDirectoryDoesNotExist($keysPath);
    }
}
