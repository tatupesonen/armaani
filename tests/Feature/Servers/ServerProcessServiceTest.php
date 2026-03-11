<?php

namespace Tests\Feature\Servers;

use App\Contracts\GameHandler;
use App\Contracts\WritesNativeLogs;
use App\GameManager;
use App\Models\Arma3Settings;
use App\Models\GameInstall;
use App\Models\Server;
use App\Services\Server\ServerBackupService;
use App\Services\Server\ServerProcessService;
use Illuminate\Support\Facades\Log;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class ServerProcessServiceTest extends TestCase
{
    use UsesTestPaths;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpTestPaths(['servers', 'games', 'mods', 'missions']);
        $this->setUpTestStoragePath();
    }

    protected function tearDown(): void
    {
        $this->tearDownTestPaths();

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // Headless Client PID Management
    // ---------------------------------------------------------------

    public function test_get_headless_client_log_path_returns_profiles_path_with_index(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        $expected = $server->getProfilesPath().'/hc_0.log';

        $this->assertEquals($expected, $service->getHeadlessClientLogPath($server, 0));
    }

    public function test_get_running_headless_client_count_returns_zero_when_no_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        $this->assertEquals(0, $service->getRunningHeadlessClientCount($server));
    }

    public function test_get_running_headless_client_count_cleans_stale_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        // Create a PID file with a non-existent process
        $pidFile = storage_path('app/server_'.$server->id.'_hc_0.pid');
        file_put_contents($pidFile, '999999999');

        $this->assertEquals(0, $service->getRunningHeadlessClientCount($server));
        $this->assertFileDoesNotExist($pidFile);
    }

    public function test_stop_all_headless_clients_removes_all_pid_files(): void
    {
        $server = $this->makeServer();
        $service = app(ServerProcessService::class);

        // Create multiple PID files with non-existent processes
        for ($i = 0; $i < 3; $i++) {
            $pidFile = storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid');
            file_put_contents($pidFile, '999999999');
        }

        $service->stopAllHeadlessClients($server);

        for ($i = 0; $i < 3; $i++) {
            $this->assertFileDoesNotExist(storage_path('app/server_'.$server->id.'_hc_'.$i.'.pid'));
        }
    }

    // ---------------------------------------------------------------
    // Launch Command
    // ---------------------------------------------------------------

    public function test_start_logs_launch_command_to_application_log(): void
    {
        $server = $this->makeServer();

        // Use a partial mock to prevent real proc_open / exec calls
        $mockService = Mockery::mock(ServerProcessService::class, [app(\App\GameManager::class), app(\App\Services\Server\ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        // Auto-backup logs "skipping backup" when no .vars file exists
        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'skipping backup'))
            ->atMost()->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, "[Server:{$server->id}") && str_contains($msg, 'Starting server from'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Launch command:'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Log file:'))
            ->once();

        $mockService->start($server);
    }

    public function test_start_uses_dev_null_for_native_log_handlers(): void
    {
        $server = $this->makeServer();

        $handler = Mockery::mock(GameHandler::class, WritesNativeLogs::class);
        $handler->shouldReceive('generateConfigFiles')->once();
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/usr/bin/true']);
        $handler->shouldReceive('getServerLogPath')->never();

        $gameManager = $this->mock(GameManager::class, function (MockInterface $mock) use ($server, $handler) {
            $mock->shouldReceive('for')
                ->with(Mockery::on(fn ($s) => $s->id === $server->id))
                ->andReturn($handler);
        });

        $mockService = Mockery::mock(ServerProcessService::class, [$gameManager, app(ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')
            ->once()
            ->withArgs(fn ($cmd, $dir, $logFile) => $logFile === '/dev/null')
            ->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Starting server from'))
            ->once();

        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Launch command:'))
            ->once();

        // The "Log file:" message should NOT be logged for native log handlers
        Log::shouldReceive('info')
            ->withArgs(fn (string $msg) => str_contains($msg, 'Log file:'))
            ->never();

        Log::shouldReceive('info')->withAnyArgs();

        $mockService->start($server);
    }

    public function test_start_does_not_truncate_log_file_for_native_log_handlers(): void
    {
        $server = $this->makeServer();

        // Create a pre-existing file at the server log path to verify it's NOT truncated
        $logPath = $server->getProfilesPath().'/server.log';
        @mkdir(dirname($logPath), 0755, true);
        file_put_contents($logPath, 'existing content');

        $handler = Mockery::mock(GameHandler::class, WritesNativeLogs::class);
        $handler->shouldReceive('generateConfigFiles')->once();
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/usr/bin/true']);

        $gameManager = $this->mock(GameManager::class, function (MockInterface $mock) use ($server, $handler) {
            $mock->shouldReceive('for')
                ->with(Mockery::on(fn ($s) => $s->id === $server->id))
                ->andReturn($handler);
        });

        $mockService = Mockery::mock(ServerProcessService::class, [$gameManager, app(ServerBackupService::class)])->makePartial();
        $mockService->shouldAllowMockingProtectedMethods();
        $mockService->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mockService->shouldReceive('startLogTail')->once();

        $mockService->start($server);

        // The existing log file should NOT have been truncated
        $this->assertEquals('existing content', file_get_contents($logPath));
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    private function makeServer(array $attributes = []): Server
    {
        $gameInstall = GameInstall::factory()->installed()->create();

        // Separate settings-level attributes from server-level attributes
        $settingsKeys = ['admin_password', 'verify_signatures', 'allowed_file_patching', 'battle_eye', 'persistent', 'von_enabled', 'additional_server_options'];
        $settingsOverrides = array_intersect_key($attributes, array_flip($settingsKeys));
        $serverAttributes = array_diff_key($attributes, $settingsOverrides);

        $server = Server::factory()->create(array_merge(
            ['game_install_id' => $gameInstall->id],
            $serverAttributes
        ));

        Arma3Settings::factory()->create(array_merge(
            ['server_id' => $server->id],
            $settingsOverrides
        ));

        return $server->refresh();
    }
}
