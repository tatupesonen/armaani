<?php

namespace App\GameHandlers;

use App\Attributes\HandlesGame;
use App\Contracts\DetectsServerState;
use App\Contracts\GameHandler;
use App\Contracts\ManagesModAssets;
use App\Contracts\SupportsBackups;
use App\Contracts\SupportsHeadlessClients;
use App\Contracts\SupportsMissions;
use App\Enums\GameType;
use App\Models\DifficultySettings;
use App\Models\NetworkSettings;
use App\Models\Server;
use App\Services\Renderer\TwigConfigRenderer;
use Illuminate\Support\Facades\Log;

#[HandlesGame(GameType::Arma3)]
final class Arma3Handler implements DetectsServerState, GameHandler, ManagesModAssets, SupportsBackups, SupportsHeadlessClients, SupportsMissions
{
    public function __construct(
        protected TwigConfigRenderer $configRenderer,
    ) {}

    public function gameType(): GameType
    {
        return GameType::Arma3;
    }

    public function serverAppId(): int
    {
        return 233780;
    }

    public function gameId(): int
    {
        return 107410;
    }

    public function defaultPort(): int
    {
        return 2302;
    }

    public function defaultQueryPort(): int
    {
        return 2303;
    }

    public function branches(): array
    {
        return ['public', 'contact', 'creatordlc', 'profiling', 'performance', 'legacy'];
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
        $binary = $this->getBinaryPath($server);
        $params = [
            $binary,
            '-port='.$server->port,
            '-name='.$this->getProfileName($server),
            '-profiles='.$server->getProfilesPath(),
            '-config='.$server->getProfilesPath().'/server.cfg',
            '-cfg='.$server->getProfilesPath().'/server_basic.cfg',
            '-nosplash',
            '-skipIntro',
            '-world=empty',
        ];

        foreach ($this->getModNames($server) as $modName) {
            $params[] = '-mod='.$modName;
        }

        if ($server->additional_params) {
            $additionalArgs = preg_split('/\s+/', trim($server->additional_params), -1, PREG_SPLIT_NO_EMPTY);
            array_push($params, ...$additionalArgs);
        }

        return $params;
    }

    public function generateConfigFiles(Server $server): void
    {
        $this->generateServerConfig($server);
        $this->generateBasicConfig($server);
        $this->generateProfileConfig($server);
    }

    public function getBinaryPath(Server $server): string
    {
        return $server->gameInstall->getInstallationPath().'/arma3server_x64';
    }

    public function getProfileName(Server $server): string
    {
        return 'arma3_'.$server->id;
    }

    public function getServerLogPath(Server $server): string
    {
        return $server->getProfilesPath().'/server.log';
    }

    // --- DetectsServerState ---

    public function getBootDetectionStrings(): array
    {
        return ['Connected to Steam servers'];
    }

    public function getModDownloadStartedString(): ?string
    {
        return null;
    }

    public function getModDownloadFinishedString(): ?string
    {
        return null;
    }

    public function getCrashDetectionStrings(): array
    {
        return [];
    }

    // --- ManagesModAssets ---

    public function symlinkMods(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $gameInstallPath = $server->gameInstall->getInstallationPath();

        // Remove existing mod symlinks (anything starting with @)
        $existingLinks = glob($gameInstallPath.'/@*') ?: [];
        foreach ($existingLinks as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }

        foreach ($preset->mods as $mod) {
            $modInstallPath = $mod->getInstallationPath();

            if (! is_dir($modInstallPath)) {
                Log::warning("[Server:{$server->id}] Mod '{$mod->name}' (ID {$mod->id}) directory not found at {$modInstallPath}, skipping symlink");

                continue;
            }

            $linkPath = $gameInstallPath.'/'.$mod->getNormalizedName();

            if (! file_exists($linkPath)) {
                symlink($modInstallPath, $linkPath);
                Log::info("[Server:{$server->id}] Symlinked mod {$mod->getNormalizedName()} -> {$modInstallPath}");
            }
        }
    }

    public function copyBiKeys(Server $server): void
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return;
        }

        $keysPath = $server->gameInstall->getInstallationPath().'/keys';

        if (! is_dir($keysPath)) {
            mkdir($keysPath, 0755, true);
        }

        foreach ($preset->mods as $mod) {
            $modKeysPath = $mod->getInstallationPath().'/keys';

            if (! is_dir($modKeysPath)) {
                continue;
            }

            foreach (glob($modKeysPath.'/*.bikey') ?: [] as $bikeyFile) {
                $destPath = $keysPath.'/'.basename($bikeyFile);

                if (is_link($destPath) && ! file_exists($destPath)) {
                    unlink($destPath);
                }

                if (! file_exists($destPath)) {
                    symlink($bikeyFile, $destPath);
                    Log::info("[Server:{$server->id}] Symlinked BiKey ".basename($bikeyFile)." from mod '{$mod->name}'");
                }
            }
        }
    }

    // --- SupportsMissions ---

    public function symlinkMissions(Server $server): void
    {
        $missionsPath = config('arma.missions_base_path');
        $mpmissionsPath = $server->gameInstall->getInstallationPath().'/mpmissions';

        if (! is_dir($missionsPath)) {
            return;
        }

        if (! is_dir($mpmissionsPath)) {
            mkdir($mpmissionsPath, 0755, true);
        }

        $existingLinks = glob($mpmissionsPath.'/*.pbo') ?: [];
        foreach ($existingLinks as $link) {
            if (is_link($link)) {
                unlink($link);
            }
        }

        $pboFiles = glob($missionsPath.'/*.pbo') ?: [];
        foreach ($pboFiles as $pboFile) {
            $linkPath = $mpmissionsPath.'/'.basename($pboFile);
            symlink($pboFile, $linkPath);
        }
    }

    // --- SupportsHeadlessClients ---

    public function buildHeadlessClientCommand(Server $server, int $index): array
    {
        $binary = $this->getBinaryPath($server);

        $params = [
            $binary,
            '-client',
            '-connect=127.0.0.1',
            '-port='.$server->port,
            '-nosound',
            '-nosplash',
            '-skipIntro',
            '-world=empty',
        ];

        if ($server->password) {
            $params[] = '-password='.$server->password;
        }

        foreach ($this->getModNames($server) as $modName) {
            $params[] = '-mod='.$modName;
        }

        return $params;
    }

    // --- SupportsBackups ---

    public function getBackupFilePath(Server $server): string
    {
        $profileName = $this->getProfileName($server);

        return $server->getProfilesPath().'/home/'.$profileName.'/'.$profileName.'.vars.Arma3Profile';
    }

    public function getBackupDownloadFilename(Server $server): string
    {
        return 'arma3_'.$server->id.'.vars.Arma3Profile';
    }

    // --- Validation ---

    public function serverValidationRules(): array
    {
        return [
            'verify_signatures' => ['boolean'],
            'allowed_file_patching' => ['boolean'],
            'battle_eye' => ['boolean'],
            'persistent' => ['boolean'],
            'von_enabled' => ['boolean'],
            'additional_server_options' => ['nullable', 'string'],
        ];
    }

    public function settingsValidationRules(): array
    {
        return [
            // Difficulty settings
            'reduced_damage' => ['boolean'],
            'group_indicators' => ['integer', 'between:0,2'],
            'friendly_tags' => ['integer', 'between:0,2'],
            'enemy_tags' => ['integer', 'between:0,2'],
            'detected_mines' => ['integer', 'between:0,2'],
            'commands' => ['integer', 'between:0,2'],
            'waypoints' => ['integer', 'between:0,2'],
            'tactical_ping' => ['integer', 'between:0,3'],
            'weapon_info' => ['integer', 'between:0,2'],
            'stance_indicator' => ['integer', 'between:0,2'],
            'stamina_bar' => ['boolean'],
            'weapon_crosshair' => ['boolean'],
            'vision_aid' => ['boolean'],
            'third_person_view' => ['integer', 'between:0,2'],
            'camera_shake' => ['boolean'],
            'score_table' => ['boolean'],
            'death_messages' => ['boolean'],
            'von_id' => ['boolean'],
            'map_content' => ['boolean'],
            'auto_report' => ['boolean'],
            'ai_level_preset' => ['integer', 'between:0,3'],
            'skill_ai' => ['numeric', 'between:0,1'],
            'precision_ai' => ['numeric', 'between:0,1'],

            // Network settings
            'max_msg_send' => ['nullable', 'integer', 'min:1'],
            'max_size_guaranteed' => ['nullable', 'integer', 'min:1'],
            'max_size_nonguaranteed' => ['nullable', 'integer', 'min:1'],
            'min_bandwidth' => ['nullable', 'integer', 'min:0'],
            'max_bandwidth' => ['nullable', 'integer', 'min:0'],
            'min_error_to_send' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'min_error_to_send_near' => ['nullable', 'numeric', 'min:0', 'max:1'],
            'max_packet_size' => ['nullable', 'integer', 'min:256'],
            'max_custom_file_size' => ['nullable', 'integer', 'min:0'],
            'terrain_grid' => ['nullable', 'numeric', 'min:0'],
            'view_distance' => ['nullable', 'integer', 'min:0'],
        ];
    }

    // --- Related Settings ---

    public function createRelatedSettings(Server $server): void
    {
        DifficultySettings::query()->create(['server_id' => $server->id]);
        NetworkSettings::query()->create(['server_id' => $server->id]);
    }

    public function updateRelatedSettings(Server $server, array $validated): void
    {
        $difficultyFields = collect($validated)->only(
            (new DifficultySettings)->getFillable()
        )->except('server_id')->toArray();

        $networkFields = collect($validated)->only(
            (new NetworkSettings)->getFillable()
        )->except('server_id')->toArray();

        if (! empty($difficultyFields)) {
            $server->difficultySettings()->updateOrCreate(
                ['server_id' => $server->id],
                $difficultyFields,
            );
        }

        if (! empty($networkFields)) {
            $server->networkSettings()->updateOrCreate(
                ['server_id' => $server->id],
                $networkFields,
            );
        }
    }

    /**
     * Get the list of normalized mod directory names from the server's active preset.
     *
     * @return array<int, string>
     */
    protected function getModNames(Server $server): array
    {
        $preset = $server->activePreset;

        if (! $preset) {
            return [];
        }

        return $preset->mods->map(fn ($mod) => $mod->getNormalizedName())->all();
    }

    /**
     * Generate and write server.cfg to the profiles directory.
     */
    protected function generateServerConfig(Server $server): void
    {
        $renderer = $this->configRenderer;

        $content = $renderer->render('arma3/server.cfg.twig', [
            'hostname' => addslashes($server->name),
            'password' => addslashes((string) $server->password),
            'admin_password' => addslashes((string) $server->admin_password),
            'max_players' => (int) $server->max_players,
            'verify_signatures' => $server->verify_signatures ? 2 : 0,
            'allowed_file_patching' => $server->allowed_file_patching ? 2 : 0,
            'disable_von' => $server->von_enabled ? 0 : 1,
            'persistent' => $server->persistent ? 1 : 0,
            'battle_eye' => $server->battle_eye ? 1 : 0,
            'motd_lines' => $server->description
                ? array_map(fn (string $line) => addslashes(trim($line)), explode("\n", $server->description))
                : null,
            'additional_server_options' => $server->additional_server_options ?: null,
        ]);

        file_put_contents(
            $server->getProfilesPath().'/server.cfg',
            $content
        );
    }

    /**
     * Generate and write server_basic.cfg (network tuning) to the profiles directory.
     */
    protected function generateBasicConfig(Server $server): void
    {
        $renderer = $this->configRenderer;
        $settings = $server->networkSettings ?? $this->getDefaultNetworkSettings();

        $content = $renderer->render('arma3/server_basic.cfg.twig', [
            'max_msg_send' => $settings->max_msg_send,
            'max_size_guaranteed' => $settings->max_size_guaranteed,
            'max_size_nonguaranteed' => $settings->max_size_nonguaranteed,
            'min_bandwidth' => $settings->min_bandwidth,
            'max_bandwidth' => $settings->max_bandwidth,
            'min_error_to_send' => $settings->min_error_to_send,
            'min_error_to_send_near' => $settings->min_error_to_send_near,
            'max_custom_file_size' => $settings->max_custom_file_size,
            'max_packet_size' => $settings->max_packet_size,
            'view_distance' => (int) $settings->view_distance,
            'terrain_grid' => (float) $settings->terrain_grid,
        ]);

        file_put_contents(
            $server->getProfilesPath().'/server_basic.cfg',
            $content
        );
    }

    /**
     * Generate the .Arma3Profile file with difficulty and AI settings.
     */
    protected function generateProfileConfig(Server $server): void
    {
        $renderer = $this->configRenderer;
        $settings = $server->difficultySettings ?? $this->getDefaultDifficultySettings();
        $profileName = $this->getProfileName($server);
        $profileDir = $server->getProfilesPath().'/home/'.$profileName;

        if (! is_dir($profileDir)) {
            mkdir($profileDir, 0755, true);
        }

        $content = $renderer->render('arma3/profile.twig', [
            'reduced_damage' => $settings->reduced_damage ? 1 : 0,
            'group_indicators' => $settings->group_indicators,
            'friendly_tags' => $settings->friendly_tags,
            'enemy_tags' => $settings->enemy_tags,
            'detected_mines' => $settings->detected_mines,
            'commands' => $settings->commands,
            'waypoints' => $settings->waypoints,
            'tactical_ping' => $settings->tactical_ping,
            'weapon_info' => $settings->weapon_info,
            'stance_indicator' => $settings->stance_indicator,
            'stamina_bar' => $settings->stamina_bar ? 1 : 0,
            'weapon_crosshair' => $settings->weapon_crosshair ? 1 : 0,
            'vision_aid' => $settings->vision_aid ? 1 : 0,
            'third_person_view' => $settings->third_person_view,
            'camera_shake' => $settings->camera_shake ? 1 : 0,
            'score_table' => $settings->score_table ? 1 : 0,
            'death_messages' => $settings->death_messages ? 1 : 0,
            'von_id' => $settings->von_id ? 1 : 0,
            'map_content' => $settings->map_content ? 1 : 0,
            'auto_report' => $settings->auto_report ? 1 : 0,
            'ai_level_preset' => $settings->ai_level_preset,
            'skill_ai' => $settings->skill_ai,
            'precision_ai' => $settings->precision_ai,
        ]);

        file_put_contents(
            $profileDir.'/'.$profileName.'.Arma3Profile',
            $content
        );
    }

    /**
     * Build a default NetworkSettings object for servers without custom network settings.
     */
    protected function getDefaultNetworkSettings(): NetworkSettings
    {
        $settings = new NetworkSettings;
        $settings->max_msg_send = 128;
        $settings->max_size_guaranteed = 512;
        $settings->max_size_nonguaranteed = 256;
        $settings->min_bandwidth = 131072;
        $settings->max_bandwidth = 10000000000;
        $settings->min_error_to_send = 0.001;
        $settings->min_error_to_send_near = 0.01;
        $settings->max_packet_size = 1400;
        $settings->max_custom_file_size = 0;
        $settings->terrain_grid = 25.0;
        $settings->view_distance = 0;

        return $settings;
    }

    /**
     * Build a default DifficultySettings object for servers without custom difficulty settings.
     */
    protected function getDefaultDifficultySettings(): DifficultySettings
    {
        $settings = new DifficultySettings;
        $settings->reduced_damage = false;
        $settings->group_indicators = 2;
        $settings->friendly_tags = 2;
        $settings->enemy_tags = 0;
        $settings->detected_mines = 2;
        $settings->commands = 2;
        $settings->waypoints = 2;
        $settings->tactical_ping = 3;
        $settings->weapon_info = 2;
        $settings->stance_indicator = 2;
        $settings->stamina_bar = true;
        $settings->weapon_crosshair = true;
        $settings->vision_aid = false;
        $settings->third_person_view = 1;
        $settings->camera_shake = true;
        $settings->score_table = true;
        $settings->death_messages = true;
        $settings->von_id = true;
        $settings->map_content = true;
        $settings->auto_report = false;
        $settings->ai_level_preset = 1;
        $settings->skill_ai = 0.50;
        $settings->precision_ai = 0.50;

        return $settings;
    }

    /**
     * Format a decimal value for config output, stripping unnecessary trailing zeros.
     */
    protected function formatDecimal(string|float $value): string
    {
        return rtrim(rtrim(number_format((float) $value, 4, '.', ''), '0'), '.');
    }
}
