<?php

namespace App\Services\Installers;

use App\Contracts\GameHandler;
use App\Contracts\GameServerInstaller;
use App\Contracts\SteamGameHandler;
use App\Models\GameInstall;
use App\Services\Steam\SteamCmdService;
use Illuminate\Support\Facades\Log;

class SteamGameInstaller implements GameServerInstaller
{
    public function __construct(
        protected SteamCmdService $steamCmd,
    ) {}

    public function install(GameInstall $install, GameHandler $handler, callable $onOutput): ?string
    {
        assert($handler instanceof SteamGameHandler);

        $installDir = $install->getInstallationPath();
        $lastPct = 0;

        $result = $this->steamCmd->installServer(
            $installDir,
            $install->branch,
            handler: $handler,
            onOutput: function (string $line) use ($onOutput, &$lastPct): void {
                $pct = $lastPct;

                if (preg_match('/progress:\s*([\d.]+)\s*\((\d+)\s*\/\s*(\d+)\)/', $line, $m)) {
                    $pct = (int) round((float) $m[1]);
                    $lastPct = $pct;
                }

                $onOutput($pct, $line);
            },
        );

        if (! $result->successful()) {
            throw new \RuntimeException('SteamCMD failed: '.$result->errorOutput());
        }

        return $this->parseBuildId($installDir, $handler, $install->id);
    }

    /**
     * Parse the build ID from the SteamCMD appmanifest ACF file.
     */
    protected function parseBuildId(string $installDir, SteamGameHandler $handler, int $installId): ?string
    {
        $manifestPath = $installDir.'/steamapps/appmanifest_'.$handler->serverAppId().'.acf';

        if (! file_exists($manifestPath)) {
            Log::warning("[GameInstall:{$installId}] Appmanifest not found at {$manifestPath}");

            return null;
        }

        $contents = file_get_contents($manifestPath);

        if ($contents !== false && preg_match('/"buildid"\s+"(\d+)"/', $contents, $matches)) {
            return $matches[1];
        }

        Log::warning("[GameInstall:{$installId}] Could not parse buildid from {$manifestPath}");

        return null;
    }
}
