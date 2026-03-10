<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Events\ModDownloadOutput;
use App\GameManager;
use App\Jobs\Concerns\InteractsWithFileSystem;
use App\Models\WorkshopMod;
use App\Services\Steam\SteamCmdService;
use App\Services\Steam\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadModJob implements ShouldQueue
{
    use InteractsWithFileSystem;
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public WorkshopMod $mod) {}

    public function handle(SteamCmdService $steamCmd, SteamWorkshopService $workshop): void
    {
        $context = "[Mod:{$this->mod->id} '{$this->mod->workshop_id}']";

        Log::info("{$context} Starting download");

        $workshop->syncMetadata($this->mod);

        $this->mod->update([
            'installation_status' => InstallationStatus::Installing,
            'progress_pct' => 0,
        ]);

        $installDir = config('arma.mods_base_path');
        $modPath = $this->mod->getInstallationPath();
        $expectedSize = $this->mod->file_size;

        $handler = app(GameManager::class)->driver($this->mod->game_type);

        $process = $steamCmd->startDownloadMod($installDir, $this->mod->workshop_id, $handler);

        ModDownloadOutput::dispatch($this->mod->id, 0, 'Starting SteamCMD download...');

        $lastProgressUpdate = -1;

        while ($process->running()) {
            sleep(1);

            if ($expectedSize > 0) {
                $currentSize = $this->getDirectorySize($modPath);
                $pct = min(99, (int) round(($currentSize / $expectedSize) * 100));

                if ($pct >= $lastProgressUpdate + 1) {
                    $lastProgressUpdate = $pct;
                    $this->mod->updateQuietly(['progress_pct' => $pct]);

                    ModDownloadOutput::dispatch(
                        $this->mod->id,
                        $pct,
                        "Downloading... {$pct}% ({$currentSize} / {$expectedSize} bytes)",
                    );
                }
            }
        }

        $result = $process->wait();

        $output = trim($result->output().' '.$result->errorOutput());
        if ($output) {
            foreach (explode("\n", $output) as $outputLine) {
                $trimmed = trim($outputLine);
                if ($trimmed !== '') {
                    ModDownloadOutput::dispatch($this->mod->id, max($lastProgressUpdate, 0), $trimmed);
                }
            }
        }

        if ($result->successful()) {
            if ($handler->requiresLowercaseConversion()) {
                $this->convertToLowercase($modPath);
            }

            $actualSize = $this->getDirectorySize($modPath);

            $this->mod->update([
                'installation_status' => InstallationStatus::Installed,
                'progress_pct' => 100,
                'installed_at' => now(),
                'file_size' => $actualSize > 0 ? $actualSize : $this->mod->file_size,
            ]);

            Log::info("{$context} Downloaded successfully (disk: {$actualSize} bytes)");

            ModDownloadOutput::dispatch($this->mod->id, 100, 'Download completed successfully.');
        } else {
            Log::error("{$context} Download failed: {$result->errorOutput()}");
            $this->mod->update(['installation_status' => InstallationStatus::Failed]);

            ModDownloadOutput::dispatch($this->mod->id, 0, 'Download failed: '.$result->errorOutput());

            $this->fail(new \RuntimeException('SteamCMD failed: '.$result->errorOutput()));
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("[Mod:{$this->mod->id} '{$this->mod->workshop_id}'] Job failed: {$exception?->getMessage()}");
        $this->mod->update(['installation_status' => InstallationStatus::Failed]);
    }
}
