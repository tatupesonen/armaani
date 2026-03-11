<?php

namespace Tests\Feature;

use App\Console\Commands\TailServerLog;
use App\Events\ServerLogOutput;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use ReflectionMethod;
use Tests\TestCase;

class TailServerLogTest extends TestCase
{
    private string $testDir;

    private TailServerLog $command;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDir = sys_get_temp_dir().'/armaani_test_tail_'.uniqid();
        mkdir($this->testDir, 0755, true);

        $this->command = new TailServerLog;
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->testDir);

        parent::tearDown();
    }

    // ---------------------------------------------------------------
    // discoverLogFiles
    // ---------------------------------------------------------------

    public function test_discover_log_files_returns_empty_when_base_dir_does_not_exist(): void
    {
        $result = $this->invokeDiscoverLogFiles('/nonexistent/path', '*.log');

        $this->assertSame([], $result);
    }

    public function test_discover_log_files_returns_empty_when_no_timestamped_subdirectories(): void
    {
        $result = $this->invokeDiscoverLogFiles($this->testDir, '*.log');

        $this->assertSame([], $result);
    }

    public function test_discover_log_files_returns_files_from_latest_directory(): void
    {
        mkdir($this->testDir.'/logs_2026-03-10_10-00-00', 0755, true);
        mkdir($this->testDir.'/logs_2026-03-11_12-00-00', 0755, true);

        file_put_contents($this->testDir.'/logs_2026-03-10_10-00-00/console.log', 'old');
        file_put_contents($this->testDir.'/logs_2026-03-11_12-00-00/console.log', 'new');
        file_put_contents($this->testDir.'/logs_2026-03-11_12-00-00/error.log', 'errors');

        $result = $this->invokeDiscoverLogFiles($this->testDir, '*.log');

        $this->assertCount(2, $result);
        $this->assertContains($this->testDir.'/logs_2026-03-11_12-00-00/console.log', $result);
        $this->assertContains($this->testDir.'/logs_2026-03-11_12-00-00/error.log', $result);
    }

    public function test_discover_log_files_respects_file_pattern(): void
    {
        mkdir($this->testDir.'/logs_2026-03-11_12-00-00', 0755, true);

        file_put_contents($this->testDir.'/logs_2026-03-11_12-00-00/console.log', 'log');
        file_put_contents($this->testDir.'/logs_2026-03-11_12-00-00/readme.txt', 'text');

        $result = $this->invokeDiscoverLogFiles($this->testDir, '*.log');

        $this->assertCount(1, $result);
        $this->assertStringEndsWith('console.log', $result[0]);
    }

    public function test_discover_log_files_picks_latest_by_name_sort(): void
    {
        mkdir($this->testDir.'/logs_2026-03-09_08-00-00', 0755, true);
        mkdir($this->testDir.'/logs_2026-03-11_12-00-00', 0755, true);
        mkdir($this->testDir.'/logs_2026-03-10_10-00-00', 0755, true);

        file_put_contents($this->testDir.'/logs_2026-03-09_08-00-00/console.log', 'oldest');
        file_put_contents($this->testDir.'/logs_2026-03-10_10-00-00/console.log', 'middle');
        file_put_contents($this->testDir.'/logs_2026-03-11_12-00-00/console.log', 'latest');

        $result = $this->invokeDiscoverLogFiles($this->testDir, '*.log');

        $this->assertCount(1, $result);
        $this->assertStringContains('logs_2026-03-11_12-00-00', $result[0]);
    }

    // ---------------------------------------------------------------
    // readAndDispatch
    // ---------------------------------------------------------------

    public function test_read_and_dispatch_dispatches_complete_lines(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "Line one\nLine two\n");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $result = $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        $this->assertTrue($result);
        $this->assertSame('', $buffer);

        Event::assertDispatched(ServerLogOutput::class, 2);
        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->line === 'Line one');
        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->line === 'Line two');
    }

    public function test_read_and_dispatch_buffers_partial_lines(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "Complete line\nPartial");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        // "Partial" should remain in the buffer since it has no trailing newline
        $this->assertSame('Partial', $buffer);

        Event::assertDispatched(ServerLogOutput::class, 1);
        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->line === 'Complete line');
    }

    public function test_read_and_dispatch_skips_empty_lines(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "First\n\n\nSecond\n");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        Event::assertDispatched(ServerLogOutput::class, 2);
    }

    public function test_read_and_dispatch_strips_carriage_returns(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "Windows line\r\n");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->line === 'Windows line');
    }

    public function test_read_and_dispatch_returns_false_when_no_new_data(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, '');

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $result = $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        $this->assertFalse($result);
        Event::assertNotDispatched(ServerLogOutput::class);
    }

    public function test_read_and_dispatch_resets_on_file_truncation(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "Initial content here\n");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        // Read the initial content
        $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        // Truncate the file (simulate log rotation)
        file_put_contents($filePath, "New\n");

        // Next read should detect truncation and reset
        $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        // After reset, reading should pick up the new content
        $result = $this->invokeReadAndDispatch($handle, $buffer, 42, $filePath);

        fclose($handle);

        $this->assertTrue($result);
        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->line === 'New');
    }

    public function test_read_and_dispatch_uses_correct_server_id(): void
    {
        Event::fake([ServerLogOutput::class]);

        $filePath = $this->testDir.'/test.log';
        file_put_contents($filePath, "Test line\n");

        $handle = fopen($filePath, 'r');
        $buffer = '';

        $this->invokeReadAndDispatch($handle, $buffer, 99, $filePath);

        fclose($handle);

        Event::assertDispatched(ServerLogOutput::class, fn ($e) => $e->serverId === 99);
    }

    // ---------------------------------------------------------------
    // Helpers
    // ---------------------------------------------------------------

    /**
     * @return list<string>
     */
    private function invokeDiscoverLogFiles(string $baseDir, string $filePattern): array
    {
        $method = new ReflectionMethod(TailServerLog::class, 'discoverLogFiles');

        return $method->invoke($this->command, $baseDir, $filePattern);
    }

    private function invokeReadAndDispatch(mixed $handle, string &$buffer, int $serverId, string $path): bool
    {
        $method = new ReflectionMethod(TailServerLog::class, 'readAndDispatch');
        $args = [$handle, &$buffer, $serverId, $path];

        return $method->invokeArgs($this->command, $args);
    }

    private function assertStringContains(string $needle, string $haystack): void
    {
        $this->assertTrue(str_contains($haystack, $needle), "Failed asserting that '{$haystack}' contains '{$needle}'.");
    }
}
