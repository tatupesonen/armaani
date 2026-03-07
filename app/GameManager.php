<?php

namespace App;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\GameHandlers\Arma3Handler;
use App\GameHandlers\DayZHandler;
use App\GameHandlers\ReforgerHandler;
use App\Models\Server;
use Illuminate\Support\Manager;

class GameManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return GameType::Arma3->value;
    }

    public function createArma3Driver(): GameHandler
    {
        return new Arma3Handler;
    }

    public function createReforgerDriver(): GameHandler
    {
        return new ReforgerHandler;
    }

    public function createDayzDriver(): GameHandler
    {
        return new DayZHandler;
    }

    /**
     * Resolve the handler for a server's game type.
     */
    public function for(Server $server): GameHandler
    {
        return $this->driver($server->game_type->value);
    }
}
