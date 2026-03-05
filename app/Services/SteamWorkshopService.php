<?php

namespace App\Services;

use App\Models\SteamAccount;
use Illuminate\Support\Facades\Http;

class SteamWorkshopService
{
    /**
     * Fetch mod metadata from the Steam Web API.
     *
     * @return array{name: string|null, file_size: int|null}|null
     */
    public function getModDetails(int $workshopId): ?array
    {
        return $this->fetchPublishedFileDetails($workshopId);
    }

    /**
     * Fetch metadata for multiple mods in a single Steam API call.
     *
     * @param  list<int>  $workshopIds
     * @return array<int, array{name: string|null, file_size: int|null}>
     */
    public function getMultipleModDetails(array $workshopIds): array
    {
        if (empty($workshopIds)) {
            return [];
        }

        $formData = ['itemcount' => count($workshopIds)];

        foreach (array_values($workshopIds) as $index => $id) {
            $formData["publishedfileids[{$index}]"] = $id;
        }

        $response = Http::asForm()->post(
            'https://api.steampowered.com/ISteamRemoteStorage/GetPublishedFileDetails/v1/',
            $formData,
        );

        if (! $response->successful()) {
            return [];
        }

        $results = [];
        $details = $response->json('response.publishedfiledetails', []);

        foreach ($details as $detail) {
            if (! $detail || ($detail['result'] ?? 0) !== 1) {
                continue;
            }

            $workshopId = (int) ($detail['publishedfileid'] ?? 0);

            if ($workshopId > 0) {
                $results[$workshopId] = [
                    'name' => $detail['title'] ?? null,
                    'file_size' => isset($detail['file_size']) ? (int) $detail['file_size'] : null,
                ];
            }
        }

        return $results;
    }

    /**
     * Validate that a Steam Web API key is accepted by the Steam Web API.
     *
     * Returns an array with 'valid' (bool) and 'error' (string|null).
     *
     * @return array{valid: bool, error: string|null}
     */
    public function validateApiKey(string $apiKey): array
    {
        $response = Http::get('https://api.steampowered.com/ISteamWebAPIUtil/GetSupportedAPIList/v1/', [
            'key' => $apiKey,
        ]);

        if ($response->successful()) {
            return ['valid' => true, 'error' => null];
        }

        return [
            'valid' => false,
            'error' => 'HTTP '.$response->status(),
        ];
    }

    /**
     * Get the configured Steam API key, preferring DB over config.
     */
    public function getApiKey(): ?string
    {
        $account = SteamAccount::query()->latest()->first();

        if ($account?->steam_api_key) {
            return $account->steam_api_key;
        }

        return config('arma.steam_api_key');
    }

    /**
     * Fetch published file details from the Steam Web API.
     *
     * @return array{name: string|null, file_size: int|null}|null
     */
    protected function fetchPublishedFileDetails(int $workshopId): ?array
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
}
