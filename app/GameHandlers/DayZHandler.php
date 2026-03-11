<?php

namespace App\GameHandlers;

use App\Attributes\Beta;
use App\Concerns\WorkshopModBehavior;
use App\Contracts\SteamGameHandler;
use App\Contracts\SupportsWorkshopMods;
use App\Models\DayZSettings;
use App\Models\Server;

#[Beta]
final class DayZHandler extends AbstractGameHandler implements SteamGameHandler, SupportsWorkshopMods
{
    use WorkshopModBehavior;

    public function __construct()
    {
        parent::__construct(
            value: 'dayz',
            label: 'DayZ',
            defaultPort: 2302,
            defaultQueryPort: 27016,
            branches: ['public', 'experimental'],
            settingsModelClass: DayZSettings::class,
            settingsRelationName: 'dayzSettings',
        );
    }

    // --- SupportsWorkshopMods ---

    public function requiresLowercaseConversion(): bool
    {
        return true;
    }

    // --- SteamGameHandler ---

    public function consumerAppId(): int
    {
        return 221100;
    }

    public function serverAppId(): int
    {
        return 223350;
    }

    public function gameId(): int
    {
        return 221100;
    }

    // --- Server Process ---

    public function buildLaunchCommand(Server $server): array
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function generateConfigFiles(Server $server): void
    {
        throw new \RuntimeException('DayZ server support is not yet implemented.');
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/DayZServer_x64';
    }

    public function getProfileName(Server $server): string
    {
        return 'dayz_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }
}
