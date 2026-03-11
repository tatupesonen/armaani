<?php

namespace Tests\Feature\GameHandlers;

use App\Contracts\SupportsWorkshopMods;
use App\GameHandlers\FactorioHandler;
use App\Models\Server;
use Illuminate\Support\Facades\Process;
use Tests\Concerns\CreatesGameScenarios;
use Tests\Concerns\GeneratesHandlerConfigs;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class FactorioHandlerTest extends TestCase
{
    use CreatesGameScenarios;
    use GeneratesHandlerConfigs;
    use UsesTestPaths;

    private FactorioHandler $handler;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTestPaths(['servers', 'games']);
        $this->handler = app(FactorioHandler::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownTestPaths();
        parent::tearDown();
    }

    // --- Identity ---

    public function test_game_type_returns_factorio(): void
    {
        $this->assertEquals('factorio', $this->handler->value());
    }

    public function test_label_returns_factorio(): void
    {
        $this->assertEquals('Factorio', $this->handler->label());
    }

    // --- DownloadsDirectly ---

    public function test_download_url_uses_branch(): void
    {
        $this->assertEquals(
            'https://factorio.com/get-download/stable/headless/linux64',
            $this->handler->getDownloadUrl('stable'),
        );
        $this->assertEquals(
            'https://factorio.com/get-download/experimental/headless/linux64',
            $this->handler->getDownloadUrl('experimental'),
        );
    }

    public function test_archive_strip_components_is_one(): void
    {
        $this->assertEquals(1, $this->handler->getArchiveStripComponents());
    }

    // --- Game Metadata ---

    public function test_default_port_returns_34197(): void
    {
        $this->assertEquals(34197, $this->handler->defaultPort());
    }

    public function test_default_query_port_returns_27015(): void
    {
        $this->assertEquals(27015, $this->handler->defaultQueryPort());
    }

    public function test_branches_returns_stable_and_experimental(): void
    {
        $this->assertSame(['stable', 'experimental'], $this->handler->branches());
    }

    public function test_does_not_support_workshop_mods(): void
    {
        $this->assertNotInstanceOf(SupportsWorkshopMods::class, $this->handler);
    }

    // --- Server Process ---

    public function test_get_binary_path(): void
    {
        $server = $this->createFactorioServer();

        $expected = $server->gameInstall->getInstallationPath().'/bin/x64/factorio';
        $this->assertEquals($expected, $this->handler->getBinaryPath($server));
    }

    public function test_get_profile_name(): void
    {
        $server = $this->createFactorioServer();

        $this->assertEquals('factorio_'.$server->id, $this->handler->getProfileName($server));
    }

    public function test_get_server_log_path(): void
    {
        $server = $this->createFactorioServer();

        $expected = $server->getProfilesPath().'/factorio-current.log';
        $this->assertEquals($expected, $this->handler->getServerLogPath($server));
    }

    // --- DetectsServerState ---

    public function test_boot_detection_string(): void
    {
        $this->assertSame(['Hosting game at'], $this->handler->getBootDetectionStrings());
    }

    public function test_crash_detection_strings_are_empty(): void
    {
        $this->assertSame([], $this->handler->getCrashDetectionStrings());
    }

    public function test_mod_download_strings_are_null(): void
    {
        $this->assertNull($this->handler->getModDownloadStartedString());
        $this->assertNull($this->handler->getModDownloadFinishedString());
    }

    public function test_should_auto_restart_returns_false(): void
    {
        $server = $this->createFactorioServer();

        $this->assertFalse($this->handler->shouldAutoRestart($server));
    }

    // --- Launch Command ---

    public function test_build_launch_command_includes_required_flags(): void
    {
        $server = $this->createFactorioServer();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertIsArray($command);
        $expectedBinary = $server->gameInstall->getInstallationPath().'/bin/x64/factorio';
        $this->assertSame($expectedBinary, $command[0]);
        $this->assertContains('--start-server', $command);
        $this->assertContains($server->getProfilesPath().'/saves/save.zip', $command);
        $this->assertContains('--server-settings', $command);
        $this->assertContains($server->getProfilesPath().'/server-settings.json', $command);
        $this->assertContains('--port', $command);
        $this->assertContains((string) $server->port, $command);
        $this->assertNotContains('--rcon-port', $command);
    }

    public function test_build_launch_command_includes_rcon_password_when_set(): void
    {
        $server = $this->createFactorioServer();
        $server->factorioSettings()->update(['rcon_password' => 'supersecret']);
        $server->refresh();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertContains('--rcon-port', $command);
        $this->assertContains((string) $server->query_port, $command);
        $this->assertContains('--rcon-password', $command);
        $this->assertContains('supersecret', $command);
    }

    public function test_build_launch_command_omits_rcon_password_when_empty(): void
    {
        $server = $this->createFactorioServer();

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertNotContains('--rcon-password', $command);
    }

    public function test_build_launch_command_includes_additional_params(): void
    {
        $server = $this->createFactorioServer(['additional_params' => '--console-log /tmp/log.txt']);

        $command = $this->handler->buildLaunchCommand($server);

        $this->assertContains('--console-log', $command);
        $this->assertContains('/tmp/log.txt', $command);
    }

    // --- Config Generation ---

    public function test_generate_config_writes_server_settings_json(): void
    {
        $server = $this->createFactorioServer([
            'name' => 'My Factory',
            'password' => 'factory123',
            'max_players' => 16,
            'description' => 'A Factorio test server',
        ]);

        $config = $this->generateAndReadConfig($server, 'server-settings.json');

        $this->assertEquals('My Factory', $config['name']);
        $this->assertEquals('A Factorio test server', $config['description']);
        $this->assertEquals('factory123', $config['game_password']);
        $this->assertEquals(16, $config['max_players']);
        $this->assertTrue($config['visibility']['public']);
        $this->assertTrue($config['visibility']['lan']);
        $this->assertTrue($config['require_user_verification']);
        $this->assertEquals('admins-only', $config['allow_commands']);
        $this->assertEquals(10, $config['autosave_interval']);
        $this->assertEquals(5, $config['autosave_slots']);
        $this->assertEquals(0, $config['afk_autokick_interval']);
        $this->assertTrue($config['auto_pause']);
        $this->assertTrue($config['only_admins_can_pause_the_game']);
    }

    public function test_generate_config_with_custom_settings(): void
    {
        $server = $this->createFactorioServer();
        $server->factorioSettings()->update([
            'visibility_public' => false,
            'allow_commands' => 'false',
            'autosave_interval' => 30,
            'auto_pause' => false,
            'tags' => 'vanilla, friendly, new players',
        ]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server, 'server-settings.json');

        $this->assertFalse($config['visibility']['public']);
        $this->assertEquals('false', $config['allow_commands']);
        $this->assertEquals(30, $config['autosave_interval']);
        $this->assertFalse($config['auto_pause']);
        $this->assertSame(['vanilla', 'friendly', 'new players'], $config['tags']);
    }

    public function test_generate_config_writes_map_gen_settings_json(): void
    {
        $server = $this->createFactorioServer();

        $config = $this->generateAndReadConfig($server, 'map-gen-settings.json');

        $this->assertEquals(0, $config['width']);
        $this->assertEquals(0, $config['height']);
        $this->assertEquals(1, $config['starting_area']);
        $this->assertFalse($config['peaceful_mode']);
        $this->assertNull($config['seed']);
        $this->assertEquals(1, $config['terrain_segmentation']);
        $this->assertEquals(1, $config['water']);

        // Resources should all default to multiplier 1 (normal)
        foreach (['coal', 'copper-ore', 'crude-oil', 'enemy-base', 'iron-ore', 'stone', 'trees', 'uranium-ore'] as $resource) {
            $this->assertEquals(1, $config['autoplace_controls'][$resource]['frequency'], "{$resource} frequency");
            $this->assertEquals(1, $config['autoplace_controls'][$resource]['size'], "{$resource} size");
            $this->assertEquals(1, $config['autoplace_controls'][$resource]['richness'], "{$resource} richness");
        }

        // Cliff defaults
        $this->assertEquals(10, $config['cliff_settings']['cliff_elevation_0']);
        $this->assertEquals(40, $config['cliff_settings']['cliff_elevation_interval']);
        $this->assertEquals(1, $config['cliff_settings']['richness']);
    }

    public function test_generate_config_with_custom_map_gen_settings(): void
    {
        $server = $this->createFactorioServer();
        $server->factorioSettings()->update([
            'iron_ore_frequency' => 'very-high',
            'iron_ore_size' => 'big',
            'iron_ore_richness' => 'high',
            'enemy_base_frequency' => 'none',
            'starting_area' => 'very-big',
            'peaceful_mode' => true,
            'map_seed' => '12345',
            'map_width' => 1000,
            'map_height' => 1000,
        ]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server, 'map-gen-settings.json');

        $this->assertEquals(6, $config['autoplace_controls']['iron-ore']['frequency']);
        $this->assertEquals(2, $config['autoplace_controls']['iron-ore']['size']);
        $this->assertEquals(2, $config['autoplace_controls']['iron-ore']['richness']);
        $this->assertEquals(0, $config['autoplace_controls']['enemy-base']['frequency']);
        $this->assertEquals(6, $config['starting_area']);
        $this->assertTrue($config['peaceful_mode']);
        $this->assertEquals(12345, $config['seed']);
        $this->assertEquals(1000, $config['width']);
        $this->assertEquals(1000, $config['height']);
    }

    public function test_generate_config_writes_map_settings_json(): void
    {
        $server = $this->createFactorioServer();

        $config = $this->generateAndReadConfig($server, 'map-settings.json');

        // User-facing settings
        $this->assertTrue($config['pollution']['enabled']);
        $this->assertTrue($config['enemy_evolution']['enabled']);
        $this->assertEquals(0.000004, $config['enemy_evolution']['time_factor']);
        $this->assertEquals(0.002, $config['enemy_evolution']['destroy_factor']);
        $this->assertEquals(0.0000009, $config['enemy_evolution']['pollution_factor']);
        $this->assertTrue($config['enemy_expansion']['enabled']);

        // Factorio 2.0 required defaults (pollution)
        $this->assertEquals(0.02, $config['pollution']['diffusion_ratio']);
        $this->assertEquals(15, $config['pollution']['min_to_diffuse']);
        $this->assertEquals(1, $config['pollution']['ageing']);
        $this->assertEquals(150, $config['pollution']['expected_max_per_chunk']);

        // Factorio 2.0 required defaults (enemy_expansion)
        $this->assertEquals(7, $config['enemy_expansion']['max_expansion_distance']);
        $this->assertEquals(5, $config['enemy_expansion']['settler_group_min_size']);
        $this->assertEquals(20, $config['enemy_expansion']['settler_group_max_size']);

        // Factorio 2.0 difficulty_settings section
        $this->assertArrayHasKey('difficulty_settings', $config);
        $this->assertEquals(1, $config['difficulty_settings']['technology_price_multiplier']);
    }

    public function test_generate_config_with_custom_gameplay_settings(): void
    {
        $server = $this->createFactorioServer();
        $server->factorioSettings()->update([
            'pollution_enabled' => false,
            'evolution_enabled' => false,
            'expansion_enabled' => false,
        ]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server, 'map-settings.json');

        $this->assertFalse($config['pollution']['enabled']);
        $this->assertFalse($config['enemy_evolution']['enabled']);
        $this->assertFalse($config['enemy_expansion']['enabled']);
    }

    // --- Save Creation ---

    public function test_generate_config_creates_save_when_none_exists(): void
    {
        Process::fake();

        $server = $this->createFactorioServer();
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        Process::assertRan(function ($process) {
            $command = $process->command;

            return str_contains(implode(' ', $command), '--create')
                && str_contains(implode(' ', $command), 'saves/save.zip')
                && str_contains(implode(' ', $command), '--map-gen-settings')
                && str_contains(implode(' ', $command), '--map-settings');
        });
    }

    public function test_generate_config_throws_with_stdout_when_save_creation_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                output: 'Error: Map gen settings file not found',
                errorOutput: '',
                exitCode: 1,
            ),
        ]);

        $server = $this->createFactorioServer();
        @mkdir($server->getProfilesPath(), 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Factorio save: Error: Map gen settings file not found');

        $this->handler->generateConfigFiles($server);
    }

    public function test_generate_config_throws_with_stderr_when_save_creation_fails(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'segfault in factorio binary',
                exitCode: 1,
            ),
        ]);

        $server = $this->createFactorioServer();
        @mkdir($server->getProfilesPath(), 0755, true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to create Factorio save: segfault in factorio binary');

        $this->handler->generateConfigFiles($server);
    }

    public function test_generate_config_skips_save_creation_when_exists(): void
    {
        Process::fake();

        $server = $this->createFactorioServer();
        $profilesPath = $server->getProfilesPath();
        $savesDir = $profilesPath.'/saves';
        @mkdir($savesDir, 0755, true);
        file_put_contents($savesDir.'/save.zip', 'fake-save-data');

        $this->handler->generateConfigFiles($server);

        Process::assertDidntRun(function ($process) {
            return str_contains(implode(' ', $process->command), '--create');
        });
    }

    // --- Scale Conversion ---

    public function test_scale_to_multiplier_converts_correctly(): void
    {
        $server = $this->createFactorioServer();
        $server->factorioSettings()->update([
            'coal_frequency' => 'none',
            'coal_size' => 'very-low',
            'coal_richness' => 'low',
            'iron_ore_frequency' => 'normal',
            'iron_ore_size' => 'high',
            'iron_ore_richness' => 'very-high',
        ]);
        $server->refresh();

        $config = $this->generateAndReadConfig($server, 'map-gen-settings.json');

        $this->assertEquals(0, $config['autoplace_controls']['coal']['frequency']);
        $this->assertEqualsWithDelta(1 / 6, $config['autoplace_controls']['coal']['size'], 0.001);
        $this->assertEquals(0.5, $config['autoplace_controls']['coal']['richness']);
        $this->assertEquals(1, $config['autoplace_controls']['iron-ore']['frequency']);
        $this->assertEquals(2, $config['autoplace_controls']['iron-ore']['size']);
        $this->assertEquals(6, $config['autoplace_controls']['iron-ore']['richness']);
    }

    // --- Related Settings ---

    public function test_create_related_settings_creates_factorio_settings(): void
    {
        $server = $this->createFactorioServer();

        $server->factorioSettings()->delete();
        $this->handler->createRelatedSettings($server);

        $this->assertNotNull($server->fresh()->factorioSettings);
    }

    public function test_update_related_settings_updates_factorio_settings(): void
    {
        $server = $this->createFactorioServer();

        $this->handler->updateRelatedSettings($server, [
            'auto_pause' => false,
            'autosave_interval' => 30,
            'rcon_password' => 'newpass',
        ]);

        $server->refresh();
        $this->assertFalse($server->factorioSettings->auto_pause);
        $this->assertEquals(30, $server->factorioSettings->autosave_interval);
        $this->assertEquals('newpass', $server->factorioSettings->rcon_password);
    }

    // --- Settings Schema ---

    public function test_settings_schema_is_not_empty(): void
    {
        $schema = $this->handler->settingsSchema();

        $this->assertNotEmpty($schema);
        $this->assertIsArray($schema);
    }

    // --- Validation Rules ---

    public function test_server_validation_rules_include_query_port(): void
    {
        $rules = $this->handler->serverValidationRules();

        $this->assertArrayHasKey('query_port', $rules);
        $this->assertArrayHasKey('password', $rules);
    }

    public function test_settings_validation_rules_include_all_settings(): void
    {
        $rules = $this->handler->settingsValidationRules();

        $this->assertArrayHasKey('rcon_password', $rules);
        $this->assertArrayHasKey('allow_commands', $rules);
        $this->assertArrayHasKey('autosave_interval', $rules);
        $this->assertArrayHasKey('coal_frequency', $rules);
        $this->assertArrayHasKey('iron_ore_size', $rules);
        $this->assertArrayHasKey('starting_area', $rules);
        $this->assertArrayHasKey('pollution_enabled', $rules);
        $this->assertArrayHasKey('evolution_time_factor', $rules);
        $this->assertArrayHasKey('expansion_enabled', $rules);
    }

    // --- Mod Presets ---

    public function test_mod_sections_returns_empty(): void
    {
        $this->assertSame([], $this->handler->modSections());
    }

    public function test_preset_mod_count_returns_zero(): void
    {
        $preset = \App\Models\ModPreset::factory()->create(['game_type' => 'factorio']);

        $this->assertEquals(0, $this->handler->getPresetModCount($preset));
    }

    /**
     * Generate config files and read a specific JSON config back as an array.
     * Fakes the Process facade to suppress save-file creation subprocesses.
     *
     * @return array<string, mixed>
     */
    private function generateAndReadConfig(Server $server, string $filename): array
    {
        Process::fake();

        return $this->generateAndReadJsonConfig($server, $filename);
    }
}
