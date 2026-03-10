<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class HttpDownloadService
{
    /**
     * Download an archive from a URL and extract it to the given directory.
     *
     * Progress is reported via the callback as an integer percentage (0–100).
     * Download accounts for 0–90%, extraction for 90–100%.
     *
     * @param  callable(int $pct, string $line): void  $onOutput
     */
    public function downloadAndExtract(
        string $url,
        string $destinationDir,
        int $stripComponents = 0,
        ?callable $onOutput = null,
    ): void {
        $tempFile = tempnam(sys_get_temp_dir(), 'armaani_dl_');

        if ($tempFile === false) {
            throw new RuntimeException('Failed to create temporary file for download');
        }

        try {
            $this->download($url, $tempFile, $onOutput);
            $this->extract($tempFile, $destinationDir, $stripComponents, $onOutput);
        } finally {
            @unlink($tempFile);
        }
    }

    /**
     * Download a file from a URL to a local path, streaming progress.
     */
    protected function download(string $url, string $destinationPath, ?callable $onOutput): void
    {
        if ($onOutput !== null) {
            $onOutput(0, "Downloading {$url}");
        }

        $fp = fopen($destinationPath, 'w');

        if ($fp === false) {
            throw new RuntimeException("Cannot open {$destinationPath} for writing");
        }

        $lastPct = 0;

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_FILE => $fp,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_FAILONERROR => true,
            CURLOPT_CONNECTTIMEOUT => 30,
            CURLOPT_TIMEOUT => 7200,
            CURLOPT_NOPROGRESS => false,
            CURLOPT_PROGRESSFUNCTION => function (
                $resource,
                int $expectedDownload,
                int $downloaded,
            ) use ($onOutput, &$lastPct): void {
                if ($expectedDownload <= 0 || $onOutput === null) {
                    return;
                }

                // Download is 0–90% of the overall progress
                $pct = (int) round(($downloaded / $expectedDownload) * 90);

                if ($pct > $lastPct) {
                    $lastPct = $pct;
                    $downloadedMb = round($downloaded / 1_048_576, 1);
                    $totalMb = round($expectedDownload / 1_048_576, 1);
                    $onOutput($pct, "Downloading: {$downloadedMb} MB / {$totalMb} MB ({$pct}%)");
                }
            },
        ]);

        $success = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        fclose($fp);

        if ($success === false) {
            throw new RuntimeException("Download failed: {$error}");
        }

        if ($httpCode >= 400) {
            throw new RuntimeException("Download failed with HTTP {$httpCode}");
        }

        $fileSize = filesize($destinationPath);
        $sizeMb = round(($fileSize ?: 0) / 1_048_576, 1);

        if ($onOutput !== null) {
            $onOutput(90, "Download complete ({$sizeMb} MB)");
        }

        Log::info("HttpDownloadService: Downloaded {$sizeMb} MB from {$url}");
    }

    /**
     * Extract a tar archive (.tar.xz, .tar.gz, .tar.bz2) to the given directory.
     */
    protected function extract(string $archivePath, string $destinationDir, int $stripComponents, ?callable $onOutput): void
    {
        if ($onOutput !== null) {
            $onOutput(91, 'Extracting archive...');
        }

        if (! is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $command = ['tar', 'xpf', $archivePath, '--directory', $destinationDir];

        if ($stripComponents > 0) {
            $command[] = '--strip-components='.$stripComponents;
        }

        Log::info('HttpDownloadService: Extracting with command: '.implode(' ', $command));

        $result = Process::timeout(600)->run($command);

        if (! $result->successful()) {
            throw new RuntimeException('Extraction failed: '.$result->errorOutput());
        }

        if ($onOutput !== null) {
            $onOutput(100, 'Extraction complete');
        }

        Log::info('HttpDownloadService: Extraction complete to '.$destinationDir);
    }
}
