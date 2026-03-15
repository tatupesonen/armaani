<?php

namespace Tests\Feature\Servers;

use App\Contracts\GameHandler;
use App\GameHandlers\AbstractGameHandler;
use App\GameManager;
use App\Models\GameInstall;
use App\Models\Server;
use App\Services\Server\ServerBackupService;
use App\Services\Server\ServerProcessService;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\UsesTestPaths;
use Tests\TestCase;

class LifecycleHooksTest extends TestCase
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
    // Start Hooks
    // ---------------------------------------------------------------

    public function test_start_calls_before_start_hook_on_handler(): void
    {
        $server = $this->makeServer();

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('beforeStart')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));

        $service = $this->makeServiceWithMockedProcess();
        $service->start($server);
    }

    public function test_start_calls_after_start_hook_on_handler(): void
    {
        $server = $this->makeServer();

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('afterStart')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));

        $service = $this->makeServiceWithMockedProcess();
        $service->start($server);
    }

    public function test_start_calls_before_start_before_spawning_process(): void
    {
        $server = $this->makeServer();
        $callOrder = [];

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('beforeStart')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'beforeStart';
        });
        $handler->shouldReceive('afterStart')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'afterStart';
        });

        $mock = Mockery::mock(ServerProcessService::class, [app(GameManager::class), app(ServerBackupService::class)])->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('startLogTail')->once();
        $mock->shouldReceive('spawnProcess')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'spawnProcess';

            return 12345;
        });

        $mock->start($server);

        $this->assertEquals(['beforeStart', 'spawnProcess', 'afterStart'], $callOrder);
    }

    // ---------------------------------------------------------------
    // Stop Hooks
    // ---------------------------------------------------------------

    public function test_stop_calls_before_stop_hook_on_handler(): void
    {
        $server = $this->makeServer();

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('beforeStop')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));

        $service = $this->makeServiceWithMockedStop();
        $service->stop($server);
    }

    public function test_stop_calls_after_stop_hook_on_handler(): void
    {
        $server = $this->makeServer();

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('afterStop')->once()->with(Mockery::on(fn ($s) => $s->id === $server->id));

        $service = $this->makeServiceWithMockedStop();
        $service->stop($server);
    }

    public function test_stop_calls_hooks_in_correct_order(): void
    {
        $server = $this->makeServer();
        $callOrder = [];

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('beforeStop')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'beforeStop';
        });
        $handler->shouldReceive('afterStop')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'afterStop';
        });

        $mock = Mockery::mock(ServerProcessService::class, [app(GameManager::class), app(ServerBackupService::class)])->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('getPid')->andReturnNull();
        $mock->shouldReceive('cleanupPidFile')->once();
        $mock->shouldReceive('stopLogTail')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'stopLogTail';
        });

        $mock->stop($server);

        $this->assertEquals('beforeStop', $callOrder[0]);
        $this->assertEquals('afterStop', end($callOrder));
    }

    // ---------------------------------------------------------------
    // Restart Hooks
    // ---------------------------------------------------------------

    public function test_restart_calls_all_four_hooks_in_order(): void
    {
        $server = $this->makeServer();
        $callOrder = [];

        $handler = $this->mockHandler($server);
        $handler->shouldReceive('beforeStop')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'beforeStop';
        });
        $handler->shouldReceive('afterStop')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'afterStop';
        });
        $handler->shouldReceive('beforeStart')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'beforeStart';
        });
        $handler->shouldReceive('afterStart')->once()->andReturnUsing(function () use (&$callOrder) {
            $callOrder[] = 'afterStart';
        });

        $service = $this->makeServiceWithMockedRestart();
        $service->restart($server);

        $this->assertEquals(['beforeStop', 'afterStop', 'beforeStart', 'afterStart'], $callOrder);
    }

    // ---------------------------------------------------------------
    // Default No-Op
    // ---------------------------------------------------------------

    public function test_abstract_handler_hooks_are_no_ops_by_default(): void
    {
        $manager = app(GameManager::class);

        foreach ($manager->allHandlers() as $driver => $handler) {
            $this->assertInstanceOf(AbstractGameHandler::class, $handler);

            $server = $this->makeServer();

            // These should not throw — they are no-ops unless overridden
            $handler->beforeStart($server);
            $handler->afterStart($server);
            $handler->beforeStop($server);
            $handler->afterStop($server);

            $this->assertTrue(true, "{$driver}: default lifecycle hooks should be no-ops");
        }
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * Create a mock GameHandler and bind it via a mocked GameManager.
     */
    private function mockHandler(Server $server): MockInterface
    {
        $handler = Mockery::mock(GameHandler::class);

        // Allow standard calls made during start/stop
        $handler->shouldReceive('generateConfigFiles')->zeroOrMoreTimes();
        $handler->shouldReceive('buildLaunchCommand')->andReturn(['/usr/bin/true'])->zeroOrMoreTimes();
        $handler->shouldReceive('getServerLogPath')->andReturn('/dev/null')->zeroOrMoreTimes();
        $handler->shouldReceive('beforeStart')->byDefault();
        $handler->shouldReceive('afterStart')->byDefault();
        $handler->shouldReceive('beforeStop')->byDefault();
        $handler->shouldReceive('afterStop')->byDefault();

        $this->mock(GameManager::class, function (MockInterface $mock) use ($server, $handler) {
            $mock->shouldReceive('for')
                ->with(Mockery::on(fn ($s) => $s->id === $server->id))
                ->andReturn($handler);
        });

        return $handler;
    }

    /**
     * Create a ServerProcessService with spawnProcess and startLogTail mocked out.
     */
    private function makeServiceWithMockedProcess(?callable $customize = null): ServerProcessService
    {
        $mock = Mockery::mock(ServerProcessService::class, [app(GameManager::class), app(ServerBackupService::class)])->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mock->shouldReceive('startLogTail')->once();

        if ($customize) {
            $customize($mock);
        }

        return $mock;
    }

    /**
     * Create a ServerProcessService with stop internals mocked out (no real process to kill).
     */
    private function makeServiceWithMockedStop(?callable $customize = null): ServerProcessService
    {
        $mock = Mockery::mock(ServerProcessService::class, [app(GameManager::class), app(ServerBackupService::class)])->makePartial();
        $mock->shouldAllowMockingProtectedMethods();
        $mock->shouldReceive('getPid')->andReturnNull();
        $mock->shouldReceive('stopLogTail')->once();
        $mock->shouldReceive('cleanupPidFile')->once();

        if ($customize) {
            $customize($mock);
        }

        return $mock;
    }

    /**
     * Create a ServerProcessService with both start and stop internals mocked.
     */
    private function makeServiceWithMockedRestart(): ServerProcessService
    {
        $mock = Mockery::mock(ServerProcessService::class, [app(GameManager::class), app(ServerBackupService::class)])->makePartial();
        $mock->shouldAllowMockingProtectedMethods();

        // Stop mocks
        $mock->shouldReceive('getPid')->andReturnNull();
        $mock->shouldReceive('stopLogTail')->once();
        $mock->shouldReceive('cleanupPidFile')->once();

        // Start mocks
        $mock->shouldReceive('spawnProcess')->once()->andReturn(12345);
        $mock->shouldReceive('startLogTail')->once();
        $mock->shouldReceive('isRunning')->andReturnFalse();

        return $mock;
    }

    private function makeServer(): Server
    {
        $gameInstall = GameInstall::factory()->installed()->create();

        return Server::factory()->create([
            'game_install_id' => $gameInstall->id,
        ]);
    }
}
