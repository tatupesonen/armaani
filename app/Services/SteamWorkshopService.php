<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class SteamWorkshopService
{
    /**
     * Fetch mod metadata from the Steam Web API.
     *
     * @return array{name: string|null, file_size: int|null}|null
     */
    public function getModDetails(int $workshopId): ?array
    {
        $apiKey = config('arma.steam_api_key');

        if (! $apiKey) {
            return $this->getModDetailsPublic($workshopId);
        }

        $response = Http::asForm()->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/', [
            'itemcount' => 1,
            'publishedfileids[0]' => $workshopId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $details = $response->json('response.publishedfiledetails.0');

        if (! $details || ($details['result'] ?? 0) !== 1) {
            return null;
        }

        return [
            'name' => $details['title'] ?? null,
            'file_size' => isset($details['file_size']) ? (int) $details['file_size'] : null,
        ];
    }

    /**
     * Fallback: fetch mod details from the public API (no API key needed).
     *
     * @return array{name: string|null, file_size: int|null}|null
     */
    protected function getModDetailsPublic(int $workshopId): ?array
    {
        $response = Http::asForm()->post('https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/', [
            'itemcount' => 1,
            'publishedfileids[0]' => $workshopId,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $details = $response->json('response.publishedfiledetails.0');

        if (! $details || ($details['result'] ?? 0) !== 1) {
            return null;
        }

        return [
            'name' => $details['title'] ?? null,
            'file_size' => isset($details['file_size']) ? (int) $details['file_size'] : null,
        ];
    }

    /**
     * Get the current size on disk for a mod's download directory (in bytes).
     */
    public function getDownloadedSize(int $workshopId): int
    {
        $path = config('arma.mods_base_path').'/steamapps/workshop/content/'.config('arma.game_id').'/'.$workshopId;

        if (! is_dir($path)) {
            return 0;
        }

        $result = Process::run(['du', '-sb', $path]);

        if (! $result->successful()) {
            return 0;
        }

        return (int) explode("\t", trim($result->output()))[0];
    }

    /**
     * Calculate download progress as a percentage (0-100).
     */
    public function getDownloadProgress(int $workshopId, ?int $expectedSize): int
    {
        if (! $expectedSize || $expectedSize <= 0) {
            return 0;
        }

        $currentSize = $this->getDownloadedSize($workshopId);

        return min(100, (int) round(($currentSize / $expectedSize) * 100));
    }
}
