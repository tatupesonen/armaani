<?php

namespace Tests\Feature\GameManager;

use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Enums\GameType;
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
        foreach (GameType::cases() as $gameType) {
            $handler = $this->manager->driver($gameType->value);

            $this->assertInstanceOf(GameHandler::class, $handler);
            $this->assertSame($gameType, $handler->gameType());
        }
    }

    public function test_discovers_arma3_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver(GameType::Arma3->value);

        $this->assertInstanceOf(Arma3Handler::class, $handler);
        $this->assertInstanceOf(DetectsServerState::class, $handler);
        $this->assertInstanceOf(ManagesModAssets::class, $handler);
        $this->assertInstanceOf(SupportsMissions::class, $handler);
        $this->assertInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_discovers_reforger_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver(GameType::ArmaReforger->value);

        $this->assertInstanceOf(ReforgerHandler::class, $handler);
        $this->assertInstanceOf(DetectsServerState::class, $handler);
        $this->assertNotInstanceOf(ManagesModAssets::class, $handler);
        $this->assertNotInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertNotInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_discovers_dayz_handler_with_correct_interfaces(): void
    {
        $handler = $this->manager->driver(GameType::DayZ->value);

        $this->assertInstanceOf(DayZHandler::class, $handler);
        $this->assertNotInstanceOf(DetectsServerState::class, $handler);
        $this->assertNotInstanceOf(ManagesModAssets::class, $handler);
        $this->assertNotInstanceOf(SupportsHeadlessClients::class, $handler);
        $this->assertNotInstanceOf(SupportsBackups::class, $handler);
    }

    public function test_throws_exception_for_unknown_driver(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('No handler registered for [nonexistent].');

        $this->manager->driver('nonexistent');
    }

    public function test_caches_discovery_across_multiple_calls(): void
    {
        $handler1 = $this->manager->driver(GameType::Arma3->value);
        $handler2 = $this->manager->driver(GameType::ArmaReforger->value);
        $handler3 = $this->manager->driver(GameType::Arma3->value);

        $this->assertInstanceOf(Arma3Handler::class, $handler1);
        $this->assertInstanceOf(ReforgerHandler::class, $handler2);
        $this->assertInstanceOf(Arma3Handler::class, $handler3);
    }
}
