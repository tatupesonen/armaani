<?php

namespace App\Services;

use App\Enums\InstallationStatus;
use App\Jobs\DownloadModJob;
use App\Models\ModPreset;
use App\Models\WorkshopMod;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PresetImportService
{
    /**
     * Parse an Arma 3 Launcher HTML preset file and return workshop mod IDs.
     *
     * @return Collection<int, int>
     */
    public function parseHtmlPreset(string $htmlContent): Collection
    {
        if (empty($htmlContent)) {
            throw new InvalidArgumentException('HTML preset content is empty.');
        }

        preg_match_all(
            '/https?:\/\/steamcommunity\.com\/sharedfiles\/filedetails\/\?id=(\d+)/',
            $htmlContent,
            $matches
        );

        if (empty($matches[1])) {
            throw new InvalidArgumentException('No workshop mod IDs found in the HTML preset file.');
        }

        return collect($matches[1])->map(fn ($id) => (int) $id)->unique()->values();
    }

    /**
     * Extract the preset name from the HTML content.
     */
    public function parsePresetName(string $htmlContent): ?string
    {
        if (preg_match('/<meta\s+name=["\']arma:presetName["\']\s+content=["\']([^"\']+)["\']/i', $htmlContent, $match)) {
            return $match[1];
        }

        if (preg_match('/<h1[^>]*>([^<]+)<\/h1>/i', $htmlContent, $match)) {
            return trim($match[1]);
        }

        return null;
    }

    /**
     * Import an HTML preset: create/find mods, create the preset, and queue downloads.
     */
    public function importFromHtml(string $htmlContent, ?string $presetName = null): ModPreset
    {
        $workshopIds = $this->parseHtmlPreset($htmlContent);
        $name = $presetName ?? $this->parsePresetName($htmlContent) ?? 'Imported Preset '.now()->format('Y-m-d H:i');

        $preset = ModPreset::query()->create(['name' => $name]);
        $modIds = [];

        foreach ($workshopIds as $workshopId) {
            $mod = WorkshopMod::query()->firstOrCreate(
                ['workshop_id' => $workshopId],
                [
                    'installation_status' => InstallationStatus::Queued,
                ]
            );

            $modIds[] = $mod->id;

            if ($mod->installation_status !== InstallationStatus::Installed) {
                $mod->update(['installation_status' => InstallationStatus::Queued]);
                DownloadModJob::dispatch($mod);
            }
        }

        $preset->mods()->sync($modIds);

        return $preset;
    }
}
