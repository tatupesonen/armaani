<?php

namespace App\Contracts;

use App\Models\Server;

/**
 * Implemented by game handlers that support scenario discovery
 * (e.g., Arma Reforger's -listScenarios binary flag).
 */
interface SupportsScenarios
{
    /**
     * Get stored scenarios for a server.
     * Should auto-discover if none exist yet.
     *
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    public function getScenarios(Server $server): array;

    /**
     * Re-run scenario discovery and return fresh results.
     *
     * @return array<int, array{value: string, name: string, isOfficial: bool}>
     */
    public function refreshScenarios(Server $server): array;
}
