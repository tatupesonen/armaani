<?php

namespace App\GameHandlers;

use App\Contracts\GameHandler;
use App\Enums\GameType;
use App\Models\Server;

class ReforgerHandler implements GameHandler
{
    public function gameType(): GameType
    {
        return GameType::ArmaReforger;
    }

    public function buildLaunchCommand(Server $server): string
    {
        $binary = $this->getBinaryPath($server);
        $configPath = $server->getProfilesPath().'/REFORGER_'.$server->id.'.json';

        $params = [
            '-config '.$configPath,
            '-maxFPS 60',
            '-backendlog',
            '-logAppend',
        ];

        if ($server->additional_params) {
            $params[] = $server->additional_params;
        }

        return $binary.' '.implode(' ', $params);
    }

    public function generateConfigFiles(Server $server): void
    {
        $settings = $server->reforgerSettings;

        $mods = [];
        $preset = $server->activePreset;

        if ($preset) {
            foreach ($preset->reforgerMods as $mod) {
                $mods[] = [
                    'modId' => $mod->mod_id,
                    'name' => $mod->name,
                ];
            }
        }

        $config = [
            'bindAddress' => '0.0.0.0',
            'bindPort' => $server->port,
            'publicAddress' => '',
            'a2s' => [
                'address' => '0.0.0.0',
                'port' => $server->query_port,
            ],
            'game' => [
                'name' => $server->name,
                'password' => (string) $server->password,
                'passwordAdmin' => (string) $server->admin_password,
                'maxPlayers' => (int) $server->max_players,
                'scenarioId' => $settings?->scenario_id ?? '',
                'gameProperties' => [
                    'battlEye' => (bool) $server->battle_eye,
                    'thirdPersonViewEnabled' => (bool) ($settings?->third_person_view_enabled ?? true),
                ],
                'mods' => $mods,
            ],
        ];

        $configPath = $server->getProfilesPath().'/REFORGER_'.$server->id.'.json';

        file_put_contents(
            $configPath,
            json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/ArmaReforgerServer';
    }

    public function getProfileName(Server $server): string
    {
        return 'reforger_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    public function getBootDetectionString(): ?string
    {
        return null;
    }

    public function symlinkMods(Server $server): void
    {
        // No-op: Reforger downloads its own mods at server startup
    }

    public function symlinkMissions(Server $server): void
    {
        // No-op: Reforger uses scenarios, not PBO mission files
    }

    public function copyBiKeys(Server $server): void
    {
        // No-op: Reforger doesn't use BiKey files
    }

    public function supportsHeadlessClients(): bool
    {
        return false;
    }

    public function buildHeadlessClientCommand(Server $server, int $index): ?string
    {
        return null;
    }

    public function getBackupFilePath(Server $server): ?string
    {
        return null;
    }

    public function getBackupDownloadFilename(Server $server): string
    {
        return 'reforger_'.$server->id.'_backup';
    }

    public function serverValidationRules(): array
    {
        return [];
    }

    public function settingsValidationRules(): array
    {
        return [
            'scenario_id' => ['nullable', 'string'],
            'third_person_view_enabled' => ['boolean'],
        ];
    }
}
