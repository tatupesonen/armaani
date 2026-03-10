<?php

namespace Tests\Feature\Servers;

use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
use App\GameManager;
use App\Models\ModPreset;
use App\Models\Server;
use App\Models\WorkshopMod;
use App\Services\Server\ServerBackupService;
use App\Services\Server\ServerProcessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesGameScenarios;
use Tests\TestCase;

class MultiGameServerTest extends TestCase
{
    use CreatesGameScenarios;
    use RefreshDatabase;

    // ---------------------------------------------------------------
    // 1. HC rejection for non-Arma3
    // ---------------------------------------------------------------

    public function test_add_headless_client_returns_null_for_reforger_server(): void
    {
        $server = $this->createReforgerServer();

        $service = app(ServerProcessService::class);
        $result = $service->addHeadlessClient($server);

        $this->assertNull($result);
    }

    public function test_add_headless_client_returns_null_for_dayz_server(): void
    {
        $server = $this->createDayZServer();

        $service = app(ServerProcessService::class);
        $result = $service->addHeadlessClient($server);

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // 2. Backup returns null for non-Arma3
    // ---------------------------------------------------------------

    public function test_create_backup_returns_null_for_reforger_server(): void
    {
        $server = $this->createReforgerServer();

        $service = app(ServerBackupService::class);
        $result = $service->createFromServer($server, 'Test backup');

        $this->assertNull($result);
    }

    public function test_create_backup_returns_null_for_dayz_server(): void
    {
        $server = $this->createDayZServer();

        $service = app(ServerBackupService::class);
        $result = $service->createFromServer($server, 'Test backup');

        $this->assertNull($result);
    }

    // ---------------------------------------------------------------
    // 3. GameManager resolves correct handler
    // ---------------------------------------------------------------

    public function test_game_manager_resolves_arma3_handler(): void
    {
        $server = $this->createArma3Server();

        $handler = app(GameManager::class)->for($server);

        $this->assertInstanceOf(Arma3Handler::class, $handler);
        $this->assertSame('arma3', $handler->value());
    }

    public function test_game_manager_resolves_reforger_handler(): void
    {
        $server = $this->createReforgerServer();

        $handler = app(GameManager::class)->for($server);

        $this->assertInstanceOf(ReforgerHandler::class, $handler);
        $this->assertSame('reforger', $handler->value());
    }

    public function test_game_manager_resolves_dayz_handler(): void
    {
        $server = $this->createDayZServer();

        $handler = app(GameManager::class)->for($server);

        $this->assertInstanceOf(DayZHandler::class, $handler);
        $this->assertSame('dayz', $handler->value());
    }

    // ---------------------------------------------------------------
    // 4. Handler game properties
    // ---------------------------------------------------------------

    public function test_arma3_handler_game_properties(): void
    {
        $handler = app(GameManager::class)->driver('arma3');

        $this->assertSame(233780, $handler->serverAppId());
        $this->assertSame(107410, $handler->gameId());
        $this->assertSame(2302, $handler->defaultPort());
        $this->assertSame(2303, $handler->defaultQueryPort());
        $this->assertSame(['public', 'contact', 'creatordlc', 'profiling', 'performance', 'legacy'], $handler->branches());
        $this->assertTrue($handler->supportsWorkshopMods());
        $this->assertTrue($handler->requiresLowercaseConversion());
    }

    public function test_reforger_handler_game_properties(): void
    {
        $handler = app(GameManager::class)->driver('reforger');

        $this->assertSame(1874900, $handler->serverAppId());
        $this->assertSame(1874900, $handler->gameId());
        $this->assertSame(2001, $handler->defaultPort());
        $this->assertSame(17777, $handler->defaultQueryPort());
        $this->assertSame(['public'], $handler->branches());
        $this->assertFalse($handler->supportsWorkshopMods());
        $this->assertFalse($handler->requiresLowercaseConversion());
    }

    public function test_dayz_handler_game_properties(): void
    {
        $handler = app(GameManager::class)->driver('dayz');

        $this->assertSame(223350, $handler->serverAppId());
        $this->assertSame(221100, $handler->gameId());
        $this->assertSame(2302, $handler->defaultPort());
        $this->assertSame(27016, $handler->defaultQueryPort());
        $this->assertSame(['public', 'experimental'], $handler->branches());
        $this->assertTrue($handler->supportsWorkshopMods());
        $this->assertTrue($handler->requiresLowercaseConversion());
    }

    // ---------------------------------------------------------------
    // 5. Composite unique workshop mod
    // ---------------------------------------------------------------

    public function test_same_workshop_id_can_exist_for_different_game_types(): void
    {
        $workshopId = 123456789;

        $arma3Mod = WorkshopMod::factory()->installed()->create([
            'game_type' => 'arma3',
            'workshop_id' => $workshopId,
            'name' => 'Shared Mod',
        ]);

        $dayzMod = WorkshopMod::factory()->installed()->dayz()->create([
            'workshop_id' => $workshopId,
            'name' => 'Shared Mod DayZ',
        ]);

        $this->assertDatabaseCount('workshop_mods', 2);
        $this->assertSame($workshopId, $arma3Mod->workshop_id);
        $this->assertSame($workshopId, $dayzMod->workshop_id);
        $this->assertSame('arma3', $arma3Mod->game_type);
        $this->assertSame('dayz', $dayzMod->game_type);
    }

    // ---------------------------------------------------------------
    // 6. Composite unique preset name
    // ---------------------------------------------------------------

    public function test_same_preset_name_can_exist_for_different_game_types(): void
    {
        $presetName = 'My Modpack';

        $arma3Preset = ModPreset::factory()->create([
            'game_type' => 'arma3',
            'name' => $presetName,
        ]);

        $dayzPreset = ModPreset::factory()->dayz()->create([
            'name' => $presetName,
        ]);

        $this->assertDatabaseCount('mod_presets', 2);
        $this->assertSame($presetName, $arma3Preset->name);
        $this->assertSame($presetName, $dayzPreset->name);
        $this->assertSame('arma3', $arma3Preset->game_type);
        $this->assertSame('dayz', $dayzPreset->game_type);
    }

    // ---------------------------------------------------------------
    // 7. Server with Reforger game install
    // ---------------------------------------------------------------

    public function test_can_create_server_with_reforger_game_install(): void
    {
        $server = $this->createReforgerServer(['name' => 'Reforger Test']);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'game_type' => 'reforger',
            'name' => 'Reforger Test',
        ]);

        $this->assertSame('reforger', $server->game_type);
        $this->assertSame('reforger', $server->gameInstall->game_type);
        $this->assertNotNull($server->reforgerSettings);
    }

    // ---------------------------------------------------------------
    // 8. Server with DayZ game install
    // ---------------------------------------------------------------

    public function test_can_create_server_with_dayz_game_install(): void
    {
        $server = $this->createDayZServer(['name' => 'DayZ Test']);

        $this->assertDatabaseHas('servers', [
            'id' => $server->id,
            'game_type' => 'dayz',
            'name' => 'DayZ Test',
        ]);

        $this->assertSame('dayz', $server->game_type);
        $this->assertSame('dayz', $server->gameInstall->game_type);
    }
}
