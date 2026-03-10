<?php

namespace Tests\Feature\GameManager;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
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

    public function test_discovers_arma3_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver('arma3');

        $this->assertInstanceOf(Arma3Handler::class, $handler);
        $this->assertInstanceOf(DetectsServerState::class, $handler);
        $this->assertInstanceOf(ManagesModAssets::class, $handler);
        $this->assertInstanceOf(SupportsMissions::class, $handler);
        $this->assertInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_discovers_reforger_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver('reforger');

        $this->assertInstanceOf(ReforgerHandler::class, $handler);
        $this->assertInstanceOf(DetectsServerState::class, $handler);
        $this->assertNotInstanceOf(ManagesModAssets::class, $handler);
        $this->assertNotInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertNotInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_discovers_dayz_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver('dayz');

        $this->assertInstanceOf(DayZHandler::class, $handler);
        $this->assertNotInstanceOf(DetectsServerState::class, $handler);
        $this->assertNotInstanceOf(ManagesModAssets::class, $handler);
        $this->assertNotInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertNotInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_throws_exception_for_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Driver [nonexistent] not supported.');

        $this->manager->driver('nonexistent');
    }

    public function test_caches_discovery_across_multiple_calls(): void
    {
        $handler1 = $this->manager->driver('arma3');
        $handler2 = $this->manager->driver('reforger');
        $handler3 = $this->manager->driver('arma3');

        $this->assertInstanceOf(Arma3Handler::class, $handler1);
        $this->assertInstanceOf(ReforgerHandler::class, $handler2);
        $this->assertInstanceOf(Arma3Handler::class, $handler3);
    }
}
