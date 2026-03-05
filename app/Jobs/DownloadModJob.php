<?php

namespace App\Jobs;

use App\Enums\InstallationStatus;
use App\Models\WorkshopMod;
use App\Services\SteamCmdService;
use App\Services\SteamWorkshopService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class DownloadModJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 3600;

    public function __construct(public WorkshopMod $mod) {}

    public function handle(SteamCmdService $steamCmd, SteamWorkshopService $workshop): void
    {
        Log::info("Starting download of mod {$this->mod->workshop_id}");

        $this->fetchMetadata($workshop);

        $this->mod->update(['installation_status' => InstallationStatus::Installing]);

        $installDir = config('arma.mods_base_path');
        $result = $steamCmd->downloadMod($installDir, $this->mod->workshop_id);

        if ($result->successful()) {
            $actualSize = $workshop->getDownloadedSize($this->mod->workshop_id);

            $this->mod->update([
                'installation_status' => InstallationStatus::Installed,
                'installed_at' => now(),
                'file_size' => $actualSize ?: $this->mod->file_size,
            ]);

            Log::info("Mod '{$this->mod->name}' (ID: {$this->mod->workshop_id}) downloaded successfully");
        } else {
            Log::error("Failed to download mod {$this->mod->workshop_id}: {$result->errorOutput()}");
            $this->mod->update(['installation_status' => InstallationStatus::Failed]);
        }
    }

    public function failed(?\Throwable $exception): void
    {
        Log::error("Mod download job failed for {$this->mod->workshop_id}: {$exception?->getMessage()}");
        $this->mod->update(['installation_status' => InstallationStatus::Failed]);
    }

    /**
     * Fetch name and expected file size from Steam API if not already set.
     */
    protected function fetchMetadata(SteamWorkshopService $workshop): void
    {
        if ($this->mod->name && $this->mod->file_size) {
            return;
        }

        $details = $workshop->getModDetails($this->mod->workshop_id);

        if ($details) {
            $this->mod->update(array_filter([
                'name' => $this->mod->name ?? $details['name'],
                'file_size' => $this->mod->file_size ?? $details['file_size'],
            ]));
        }
    }
}
