<?php

namespace App\Contracts;

use App\Enums\GameType;
use App\Models\Server;

interface GameHandler
{
    public function gameType(): GameType;

    // --- Game Metadata ---

    /**
     * Steam App ID for the dedicated server binary.
     */
    public function serverAppId(): int;

    /**
     * Steam Game ID (used for workshop mod downloads).
     */
    public function gameId(): int;

    /**
     * Default game port for new servers.
     */
    public function defaultPort(): int;

    /**
     * Default Steam query port for new servers.
     */
    public function defaultQueryPort(): int;

    /**
     * Available SteamCMD beta branches for this game.
     *
     * @return list<string>
     */
    public function branches(): array;

    /**
     * Whether this game uses Steam Workshop mods downloaded via SteamCMD.
     */
    public function supportsWorkshopMods(): bool;

    /**
     * Whether mod files need to be converted to lowercase (Linux requirement).
     */
    public function requiresLowercaseConversion(): bool;

    // --- Server Process ---

    /**
     * Build the command arguments to start the game server.
     *
     * @return array<int, string> The binary path as the first element, followed by arguments.
     */
    public function buildLaunchCommand(Server $server): array;

    /**
     * Generate all config files needed by this game (server.cfg, JSON config, profiles, etc.)
     * Called on every server start.
     */
    public function generateConfigFiles(Server $server): void;

    /**
     * Get the full path to the server executable.
     */
    public function getBinaryPath(Server $server): string;

    /**
     * Get the profile name used for this server (e.g., 'arma3_1').
     */
    public function getProfileName(Server $server): string;

    /**
     * Get the path to the server's log file.
     */
    public function getServerLogPath(Server $server): string;

    // --- Validation ---

    /**
     * Validation rules for game-specific server fields.
     * Merged with common server validation rules in the form request.
     */
    public function serverValidationRules(): array;

    /**
     * Validation rules for game-specific settings (difficulty, network, reforger settings, etc.)
     */
    public function settingsValidationRules(): array;

    // --- Related Settings ---

    /**
     * Create default related settings models for a newly created server.
     * Called from ServerController::store().
     */
    public function createRelatedSettings(Server $server): void;

    /**
     * Update game-specific related settings from validated request data.
     * Called from ServerController::update().
     */
    public function updateRelatedSettings(Server $server, array $validated): void;
}
