<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Events\GameInstallOutput;
use App\GameManager;
use App\Jobs\Concerns\InteractsWithFileSystem;
use App\Models\GameInstall;
use App\Services\Installers\InstallerResolver;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class InstallServerJob implements ShouldQueue
{
    use InteractsWithFileSystem;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 7200;

    public function __construct(public GameInstall $gameInstall) {}

    public function handle(): void
    {
        $installDir = $this->gameInstall->getInstallationPath();

        if (! is_dir($installDir)) {
            mkdir($installDir, 0755, true);
        }

        $this->gameInstall->update([
            'installation_status' => InstallationStatus::Installing,
            'progress_pct' => 0,
        ]);

        $context = "[GameInstall:{$this->gameInstall->id} '{$this->gameInstall->name}']";

        Log::info("{$context} Starting installation (branch: {$this->gameInstall->branch}) to {$installDir}");

        $handler = app(GameManager::class)->driver($this->gameInstall->game_type);

        $installer = app(InstallerResolver::class)->resolve($handler);

        try {
            $lastPctWritten = 0;

            $buildId = $installer->install($this->gameInstall, $handler, function (int $pct, string $line) use (&$lastPctWritten, $context): void {
                Log::info("{$context} {$line}");

                if ($pct >= $lastPctWritten + 1 || $pct === 100) {
                    $lastPctWritten = $pct;
                    $this->gameInstall->updateQuietly(['progress_pct' => $pct]);
                }

                GameInstallOutput::dispatch($this->gameInstall->id, $pct, $line);
            });
        } catch (\Throwable $e) {
            $this->gameInstall->update(['installation_status' => InstallationStatus::Failed]);

            Log::error("{$context} Installation failed: {$e->getMessage()}");

            GameInstallOutput::dispatch($this->gameInstall->id, 0, "Installation failed: {$e->getMessage()}");

            $this->fail($e);

            return;
        }

        $diskSize = $this->getDirectorySize($installDir);

        $this->gameInstall->update([
            'installation_status' => InstallationStatus::Installed,
            'progress_pct' => 100,
            'disk_size_bytes' => $diskSize > 0 ? $diskSize : $this->gameInstall->disk_size_bytes,
            'build_id' => $buildId,
            'installed_at' => now(),
        ]);

        Log::info("{$context} Completed successfully (disk: {$diskSize} bytes, build: {$buildId})");

        GameInstallOutput::dispatch($this->gameInstall->id, 100, 'Installation completed successfully.');
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("[GameInstall:{$this->gameInstall->id}] Job failed: ".($exception?->getMessage() ?? 'Unknown error'));
        $this->gameInstall->update(['installation_status' => InstallationStatus::Failed]);
    }
}
