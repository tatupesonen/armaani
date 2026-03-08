<?php

namespace Tests\Concerns;

use App\Enums\GameType;
use App\Models\GameInstall;
use App\Models\Server;

trait CreatesGameScenarios
{
    protected function createArma3Server(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->create(['game_type' => GameType::Arma3]);
        $server = Server::factory()->create(array_merge([
            'game_type' => GameType::Arma3,
            'game_install_id' => $install->id,
        ], $overrides));
        $server->difficultySettings()->create([]);
        $server->networkSettings()->create([]);

        return $server->load('gameInstall', 'difficultySettings', 'networkSettings');
    }

    protected function createReforgerServer(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->reforger()->create();
        $server = Server::factory()->create(array_merge([
            'game_type' => GameType::ArmaReforger,
            'game_install_id' => $install->id,
            'port' => 2001,
            'query_port' => 17777,
        ], $overrides));
        $server->reforgerSettings()->create(['scenario_id' => '{ECC61978EDCC2B5A}Missions/23_Campaign.conf']);

        return $server->load('gameInstall', 'reforgerSettings');
    }

    protected function createDayZServer(array $overrides = []): Server
    {
        $install = GameInstall::factory()->installed()->dayz()->create();

        return Server::factory()->create(array_merge([
            'game_type' => GameType::DayZ,
            'game_install_id' => $install->id,
            'query_port' => 27016,
        ], $overrides));
    }
}
