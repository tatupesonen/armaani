<?php

namespace Tests\Feature\Servers;

use App\Models\GameInstall;
use App\Models\Server;
use App\Services\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class ServerProcessServiceTest extends TestCase
{
    use RefreshDatabase;

    private ServerProcessService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new ServerProcessService;
    }

    public function test_generate_server_config_writes_file_to_profiles_path(): void
    {
        $server = $this->makeServer([
            'name' => 'My Test Server',
            'password' => 'secret',
            'admin_password' => 'adminpass',
            'max_players' => 32,
            'description' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $this->assertFileExists($profilesPath.'/server.cfg');

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "My Test Server";', $contents);
        $this->assertStringContainsString('password = "secret";', $contents);
        $this->assertStringContainsString('passwordAdmin = "adminpass";', $contents);
        $this->assertStringContainsString('maxPlayers = 32;', $contents);
    }

    public function test_generate_server_config_empty_password_fields_are_empty_strings(): void
    {
        $server = $this->makeServer([
            'password' => null,
            'admin_password' => null,
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('password = "";', $contents);
        $this->assertStringContainsString('passwordAdmin = "";', $contents);
    }

    public function test_generate_server_config_includes_motd_when_description_set(): void
    {
        $server = $this->makeServer([
            'description' => "Welcome to the server\nHave fun",
        ]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('motd[] = {', $contents);
        $this->assertStringContainsString('"Welcome to the server"', $contents);
        $this->assertStringContainsString('"Have fun"', $contents);
    }

    public function test_generate_server_config_no_motd_when_description_empty(): void
    {
        $server = $this->makeServer(['description' => null]);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('motd[]', $contents);
    }

    public function test_generate_server_config_overwrites_existing_file(): void
    {
        $server = $this->makeServer(['name' => 'Original Name']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);
        file_put_contents($profilesPath.'/server.cfg', 'old content');

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringNotContainsString('old content', $contents);
        $this->assertStringContainsString('hostname = "Original Name";', $contents);
    }

    public function test_generate_server_config_escapes_special_characters_in_name(): void
    {
        $server = $this->makeServer(['name' => 'Server with "quotes"']);

        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->invokeGenerateServerConfig($server);

        $contents = file_get_contents($profilesPath.'/server.cfg');
        $this->assertStringContainsString('hostname = "Server with \\"quotes\\"";', $contents);
    }

    public function test_symlink_missions_creates_symlinks_in_game_install_mpmissions_directory(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        file_put_contents($missionsPath.'/co40_Domination.Altis.pbo', 'fake');
        file_put_contents($missionsPath.'/tvt20_Ambush.Stratis.pbo', 'fake');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->invokeSymlinkMissions($server);

        $this->assertDirectoryExists($mpmissionsPath);
        $this->assertTrue(is_link($mpmissionsPath.'/co40_Domination.Altis.pbo'));
        $this->assertTrue(is_link($mpmissionsPath.'/tvt20_Ambush.Stratis.pbo'));
        $this->assertEquals($missionsPath.'/co40_Domination.Altis.pbo', readlink($mpmissionsPath.'/co40_Domination.Altis.pbo'));
    }

    public function test_symlink_missions_removes_stale_symlinks(): void
    {
        $server = $this->makeServer();
        $missionsPath = $this->missionsPath;
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        mkdir($missionsPath, 0755, true);
        mkdir($mpmissionsPath, 0755, true);

        file_put_contents($missionsPath.'/new_mission.pbo', 'fake');
        file_put_contents($missionsPath.'/stale_target.pbo', 'fake');
        symlink($missionsPath.'/stale_target.pbo', $mpmissionsPath.'/stale_target.pbo');

        unlink($missionsPath.'/stale_target.pbo');

        config(['arma.missions_base_path' => $missionsPath]);

        $this->invokeSymlinkMissions($server);

        $this->assertTrue(is_link($mpmissionsPath.'/new_mission.pbo'));
        $this->assertFalse(file_exists($mpmissionsPath.'/stale_target.pbo'));
    }

    public function test_symlink_missions_skips_when_missions_directory_does_not_exist(): void
    {
        $server = $this->makeServer();
        $mpmissionsPath = $server->getBinaryPath().'/mpmissions';

        config(['arma.missions_base_path' => '/nonexistent/path']);

        $this->invokeSymlinkMissions($server);

        $this->assertDirectoryDoesNotExist($mpmissionsPath);
    }

    protected function tearDown(): void
    {
        $servers = Server::all();
        foreach ($servers as $server) {
            $profilesPath = $server->getProfilesPath();
            if (is_dir($profilesPath)) {
                @unlink($profilesPath.'/server.cfg');
                @rmdir($profilesPath);
            }

            foreach ([$server->getInstallationPath(), $server->getBinaryPath()] as $basePath) {
                $mpmissionsPath = $basePath.'/mpmissions';
                if (is_dir($mpmissionsPath)) {
                    $files = glob($mpmissionsPath.'/*') ?: [];
                    foreach ($files as $file) {
                        @unlink($file);
                    }
                    @rmdir($mpmissionsPath);
                }
            }
        }

        if (isset($this->missionsPath) && is_dir($this->missionsPath)) {
            $files = glob($this->missionsPath.'/*') ?: [];
            foreach ($files as $file) {
                @unlink($file);
            }
            @rmdir($this->missionsPath);
        }

        parent::tearDown();
    }

    public function test_get_server_log_path_returns_profiles_path(): void
    {
        $server = $this->makeServer();

        $expected = $server->getProfilesPath().'/server.log';

        $this->assertEquals($expected, $this->service->getServerLogPath($server));
    }

    public function test_get_headless_client_log_path_returns_profiles_path_with_index(): void
    {
        $server = $this->makeServer();

        $expected = $server->getProfilesPath().'/hc_0.log';

        $this->assertEquals($expected, $this->service->getHeadlessClientLogPath($server, 0));
    }

    public function test_start_logs_launch_command_to_application_log(): void
    {
        $server = $this->makeServer();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, "[Server:{$server->id}") && str_contains($msg, 'Starting server from'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Launch command:'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Log file:'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Process started with PID'))
            ->once();

        $this->service->start($server);
    }

    public function test_build_launch_command_uses_game_install_binary_path(): void
    {
        $server = $this->makeServer();

        $reflection = new \ReflectionMethod(ServerProcessService::class, 'buildLaunchCommand');
        $command = $reflection->invoke($this->service, $server);

        $expectedBinary = $server->getBinaryPath().'/arma3server_x64';
        $this->assertStringStartsWith($expectedBinary, $command);
        $this->assertStringContainsString('-port='.$server->port, $command);
        $this->assertStringContainsString('-profiles='.$server->getProfilesPath(), $command);
    }

    private string $missionsPath;

    private function makeServer(array $attributes = []): Server
    {
        $this->missionsPath = storage_path('arma/missions_test_'.uniqid());

        $gameInstall = GameInstall::factory()->installed()->create();

        return Server::factory()->create(array_merge(
            ['game_install_id' => $gameInstall->id],
            $attributes
        ));
    }

    private function invokeGenerateServerConfig(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'generateServerConfig');
        $reflection->invoke($this->service, $server);
    }

    private function invokeSymlinkMissions(Server $server): void
    {
        $reflection = new \ReflectionMethod(ServerProcessService::class, 'symlinkMissions');
        $reflection->invoke($this->service, $server);
    }
}
