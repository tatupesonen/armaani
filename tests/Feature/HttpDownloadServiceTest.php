<?php

namespace Tests\Feature;

use App\Services\HttpDownloadService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use RuntimeException;
use Tests\TestCase;

class HttpDownloadServiceTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir().'/armaani_test_http_'.uniqid();
        @mkdir($this->testDir, 0755, true);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);

        parent::tearDown();
    }

    // --- Extraction ---

    public function test_extract_runs_tar_with_preserve_permissions(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';

        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir);

        Process::assertRan(function ($process) use ($destDir) {
            $command = implode(' ', $process->command);

            return str_contains($command, 'tar xpf')
                && str_contains($command, '--directory '.$destDir);
        });
    }

    public function test_extract_includes_strip_components_when_specified(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';

        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir, stripComponents: 1);

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return str_contains($command, '--strip-components=1');
        });
    }

    public function test_extract_omits_strip_components_when_zero(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';

        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir, stripComponents: 0);

        Process::assertRan(function ($process) {
            $command = implode(' ', $process->command);

            return ! str_contains($command, '--strip-components');
        });
    }

    public function test_extract_creates_destination_directory_if_missing(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/nested/deep/dir';

        $this->assertDirectoryDoesNotExist($destDir);

        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir);

        $this->assertDirectoryExists($destDir);
    }

    public function test_extract_throws_on_tar_failure(): void
    {
        Process::fake([
            '*' => Process::result(
                output: '',
                errorOutput: 'tar: Error is not recoverable: exiting now',
                exitCode: 2,
            ),
        ]);

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Extraction failed: tar: Error is not recoverable: exiting now');

        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir);
    }

    // --- Progress Callback ---

    public function test_extraction_reports_progress_via_callback(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';
        $progressUpdates = [];

        $service->downloadAndExtract(
            'https://example.com/server.tar.xz',
            $destDir,
            onOutput: function (int $pct, string $line) use (&$progressUpdates) {
                $progressUpdates[] = ['pct' => $pct, 'line' => $line];
            },
        );

        // Should include extraction progress messages (91% and 100%)
        $pcts = array_column($progressUpdates, 'pct');
        $this->assertContains(91, $pcts, 'Should report 91% for extraction start');
        $this->assertContains(100, $pcts, 'Should report 100% for extraction complete');

        $lines = array_column($progressUpdates, 'line');
        $this->assertTrue(
            in_array('Extracting archive...', $lines),
            'Should report extraction start message',
        );
        $this->assertTrue(
            in_array('Extraction complete', $lines),
            'Should report extraction complete message',
        );
    }

    public function test_works_without_progress_callback(): void
    {
        Process::fake();

        $service = $this->makeServiceWithFakeDownload();
        $destDir = $this->testDir.'/extracted';

        // Should not throw when no callback is provided
        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir, onOutput: null);

        Process::assertRan(function ($process) {
            return str_contains(implode(' ', $process->command), 'tar xpf');
        });
    }

    // --- Temp File Cleanup ---

    public function test_cleans_up_temp_file_on_success(): void
    {
        Process::fake();

        $createdTempFiles = [];

        $service = new class extends HttpDownloadService
        {
            /** @var list<string> */
            public array $tempFiles = [];

            protected function download(string $url, string $destinationPath, ?callable $onOutput): void
            {
                $this->tempFiles[] = $destinationPath;
                // Write something so the file exists
                file_put_contents($destinationPath, 'fake-archive-data');
            }
        };

        $destDir = $this->testDir.'/extracted';
        $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir);

        // Temp file should be cleaned up
        foreach ($service->tempFiles as $tempFile) {
            $this->assertFileDoesNotExist($tempFile, 'Temp file should be cleaned up after success');
        }
    }

    public function test_cleans_up_temp_file_on_extraction_failure(): void
    {
        Process::fake([
            '*' => Process::result(output: '', errorOutput: 'extraction error', exitCode: 1),
        ]);

        $service = new class extends HttpDownloadService
        {
            /** @var list<string> */
            public array $tempFiles = [];

            protected function download(string $url, string $destinationPath, ?callable $onOutput): void
            {
                $this->tempFiles[] = $destinationPath;
                file_put_contents($destinationPath, 'fake-archive-data');
            }
        };

        $destDir = $this->testDir.'/extracted';

        try {
            $service->downloadAndExtract('https://example.com/server.tar.xz', $destDir);
        } catch (RuntimeException) {
            // Expected
        }

        foreach ($service->tempFiles as $tempFile) {
            $this->assertFileDoesNotExist($tempFile, 'Temp file should be cleaned up even after failure');
        }
    }

    // --- Download Errors ---

    public function test_download_throws_on_curl_failure(): void
    {
        // Use an unreachable URL to trigger a curl error
        $service = new HttpDownloadService;
        $destDir = $this->testDir.'/extracted';

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Download failed');

        $service->downloadAndExtract('https://0.0.0.0:1/nonexistent.tar.xz', $destDir);
    }

    // --- Full Integration (real tar) ---

    public function test_full_download_and_extract_with_real_tar(): void
    {
        // Create a real tar.gz archive to test extraction
        $archiveSourceDir = $this->testDir.'/source';
        @mkdir($archiveSourceDir.'/subdir', 0755, true);
        file_put_contents($archiveSourceDir.'/subdir/test.txt', 'hello from test');
        file_put_contents($archiveSourceDir.'/subdir/run.sh', '#!/bin/bash');
        chmod($archiveSourceDir.'/subdir/run.sh', 0755);

        $archivePath = $this->testDir.'/test-archive.tar.gz';

        // Create the tar archive
        $result = \Illuminate\Support\Facades\Process::run([
            'tar', 'czf', $archivePath,
            '--directory', $archiveSourceDir,
            'subdir',
        ]);
        $this->assertTrue($result->successful(), 'Failed to create test archive');

        // Use a partial mock: override download to copy the pre-built archive
        $service = new class($archivePath) extends HttpDownloadService
        {
            public function __construct(private string $archiveSourcePath) {}

            protected function download(string $url, string $destinationPath, ?callable $onOutput): void
            {
                copy($this->archiveSourcePath, $destinationPath);
                if ($onOutput !== null) {
                    $onOutput(90, 'Download complete (0 MB)');
                }
            }
        };

        $destDir = $this->testDir.'/extracted';
        $progressUpdates = [];

        $service->downloadAndExtract(
            'https://example.com/server.tar.gz',
            $destDir,
            stripComponents: 1,
            onOutput: function (int $pct, string $line) use (&$progressUpdates) {
                $progressUpdates[] = ['pct' => $pct, 'line' => $line];
            },
        );

        // Files should be extracted with strip-components=1 (subdir stripped)
        $this->assertFileExists($destDir.'/test.txt');
        $this->assertEquals('hello from test', file_get_contents($destDir.'/test.txt'));

        // Executable permission should be preserved (xpf flag)
        $this->assertFileExists($destDir.'/run.sh');
        $this->assertTrue(is_executable($destDir.'/run.sh'), 'Executable permission should be preserved');

        // Progress should reach 100%
        $pcts = array_column($progressUpdates, 'pct');
        $this->assertContains(100, $pcts);
    }

    /**
     * Create a service that skips the actual curl download.
     * The download() method is replaced with a no-op that creates an empty file.
     */
    private function makeServiceWithFakeDownload(): HttpDownloadService
    {
        return new class extends HttpDownloadService
        {
            protected function download(string $url, string $destinationPath, ?callable $onOutput): void
            {
                // Create a fake file so the tar command has something to reference
                file_put_contents($destinationPath, '');

                if ($onOutput !== null) {
                    $onOutput(0, "Downloading {$url}");
                    $onOutput(90, 'Download complete (0 MB)');
                }
            }
        };
    }
}
