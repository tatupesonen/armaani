<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class VersionCheckService
{
    private const GITHUB_REPO = 'tatupesonen/Armaani';

    /**
     * Check if the current version is the latest available on GitHub.
     *
     * @return array{current: string, latest: string|null, update_available: bool, error: string|null}
     */
    public function check(): array
    {
        $currentVersion = (string) config('app.version');

        try {
            $response = Http::timeout(5)
                ->withHeaders(['Accept' => 'application/vnd.github+json'])
                ->get('https://api.github.com/repos/'.self::GITHUB_REPO.'/releases/latest');

            if ($response->status() === 404) {
                return [
                    'current' => $currentVersion,
                    'latest' => null,
                    'update_available' => false,
                    'error' => 'No releases found on GitHub.',
                ];
            }

            if ($response->failed()) {
                return [
                    'current' => $currentVersion,
                    'latest' => null,
                    'update_available' => false,
                    'error' => "GitHub API returned HTTP {$response->status()}.",
                ];
            }

            $tagName = $response->json('tag_name');

            if (! is_string($tagName) || $tagName === '') {
                return [
                    'current' => $currentVersion,
                    'latest' => null,
                    'update_available' => false,
                    'error' => 'GitHub release is missing a tag name.',
                ];
            }

            $latestVersion = ltrim($tagName, 'v');

            return [
                'current' => $currentVersion,
                'latest' => $latestVersion,
                'update_available' => version_compare($currentVersion, $latestVersion, '<'),
                'error' => null,
            ];
        } catch (ConnectionException $e) {
            return [
                'current' => $currentVersion,
                'latest' => null,
                'update_available' => false,
                'error' => $e->getMessage(),
            ];
        }
    }
}
