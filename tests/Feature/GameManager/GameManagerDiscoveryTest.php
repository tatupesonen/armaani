<?php

namespace Tests\Feature\GameManager;

use App\Contracts\GameHandler;
use App\GameManager;
use Tests\TestCase;

class GameManagerDiscoveryTest extends TestCase
{
    private GameManager $manager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->manager = app(GameManager::class);
    }

    public function test_discovers_all_game_handlers(): void
    {
        foreach ($this->manager->allHandlers() as $driver => $handler) {
            $this->assertInstanceOf(GameHandler::class, $handler);
            $this->assertSame($driver, $handler->value());
        }
    }

    public function test_throws_exception_for_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [nonexistent] not supported.');

        $this->manager->driver('nonexistent');
    }

    public function test_caches_discovery_across_multiple_calls(): void
    {
        $handlers = $this->manager->allHandlers();
        $firstDriver = array_key_first($handlers);

        $handler1 = $this->manager->driver($firstDriver);
        $handler2 = $this->manager->driver($firstDriver);

        $this->assertSame($handler1, $handler2, 'Same driver should return the same cached instance');
    }
}
