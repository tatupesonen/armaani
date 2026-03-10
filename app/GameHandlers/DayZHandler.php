<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Contracts\SteamGameHandler;
use App\Models\DayZSettings;
use App\Models\ModPreset;
use App\Models\Server;

final class DayZHandler implements GameHandler, SteamGameHandler
{
    public function value(): string
    {
        return 'dayz';
    }

    public function label(): string
    {
        return 'DayZ';
    }

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

    public function defaultPort(): int
    {
        return 2302;
    }

    public function defaultQueryPort(): int
    {
        return 27016;
    }

    public function branches(): array
    {
        return ['public', 'experimental'];
    }

    public function supportsWorkshopMods(): bool
    {
        return true;
    }

    public function requiresLowercaseConversion(): bool
    {
        return true;
    }

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

    // --- UI Schema ---

    public function settingsSchema(): array
    {
        return [];
    }

    // --- Validation ---

    public function serverValidationRules(?Server $server = null): array
    {
        return [];
    }

    public function settingsValidationRules(): array
    {
        return [];
    }

    // --- Related Settings ---

    /** @phpstan-ignore return.unusedType */
    public function settingsModelClass(): ?string
    {
        return DayZSettings::class;
    }

    /** @phpstan-ignore return.unusedType */
    public function settingsRelationName(): ?string
    {
        return 'dayzSettings';
    }

    public function createRelatedSettings(Server $server): void
    {
        DayZSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $dayzFields = collect($validated)->only(
            (new DayZSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($dayzFields)) {
            $server->dayzSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $dayzFields,
            );
        }
    }

    // --- Mod Presets ---

    public function modSections(): array
    {
        return [
            [
                'type' => 'workshop',
                'label' => 'Workshop Mods',
                'relationship' => 'mods',
                'formField' => 'mod_ids',
            ],
        ];
    }

    public function syncPresetMods(ModPreset $preset, array $validated): void
    {
        $preset->mods()->sync($validated['mod_ids'] ?? []);
    }

    public function getPresetModCount(ModPreset $preset): int
    {
        return $preset->mods()->count();
    }
}
