<?php

namespace App;

use App\Contracts\GameHandler;
use App\Contracts\SteamGameHandler;
use App\Models\Server;
use Illuminate\Support\Manager;

class GameManager extends Manager
{
    public function getDefaultDriver(): string
    {
        return array_key_first($this->customCreators) ?? 'arma3';
    }

    /**
     * Resolve the handler for a server's game type.
     */
    public function for(Server $server): GameHandler
    {
        return $this->driver($server->game_type);
    }

    /**
     * Get all registered game handlers.
     *
     * @return array<string, GameHandler>
     */
    public function allHandlers(): array
    {
        $handlers = [];

        foreach (array_keys($this->customCreators) as $key) {
            $handlers[$key] = $this->driver($key);
        }

        return $handlers;
    }

    /**
     * Get all available game type string values.
     *
     * @return list<string>
     */
    public function availableTypes(): array
    {
        return array_keys($this->customCreators);
    }

    /**
     * Resolve a game handler by Steam consumer App ID.
     * Returns null if no handler matches.
     */
    public function fromConsumerAppId(int $appId): ?GameHandler
    {
        foreach ($this->allHandlers() as $handler) {
            if ($handler instanceof SteamGameHandler && $handler->consumerAppId() === $appId) {
                return $handler;
            }
        }

        return null;
    }
}
