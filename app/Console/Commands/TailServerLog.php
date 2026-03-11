<?php

namespace App\Console\Commands;

use App\Contracts\WritesNativeLogs;
use App\Events\ServerLogOutput;
use App\GameManager;
use App\Models\Server;
use Illuminate\Console\Command;

class TailServerLog extends Command
{
    protected $signature = 'server:tail-log {server}';

    protected $description = 'Tail a running server log file and broadcast new lines via WebSocket';

    public function handle(): int
    {
        $server = Server::query()->findOrFail($this->argument('server'));
        $handler = app(GameManager::class)->for($server);

        if ($handler instanceof WritesNativeLogs) {
            return $this->tailNativeLogs($server, $handler);
        }

        return $this->tailSingleFile($server, $handler->getServerLogPath($server));
    }

    /**
     * Tail a single log file (stdout capture). Used for games that don't write native logs.
     */
    protected function tailSingleFile(Server $server, string $logPath): int
    {
        if (! file_exists($logPath)) {
            $this->error("Log file not found: {$logPath}");

            return self::FAILURE;
        }

        $this->info("Tailing log for server '{$server->name}' (ID: {$server->id})");
        $this->info("File: {$logPath}");

        $handle = fopen($logPath, 'r');

        if (! $handle) {
            $this->error("Could not open log file: {$logPath}");

            return self::FAILURE;
        }

        fseek($handle, 0, SEEK_END);

        $buffer = '';

        while (true) {
            $this->readAndDispatch($handle, $buffer, $server->id, $logPath);
            usleep(250_000);
        }

        // @codeCoverageIgnoreStart
        fclose($handle);

        return self::SUCCESS;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Tail native log files written by the game engine.
     *
     * Discovers the latest timestamped log directory, then tails all matching
     * log files within it. Handles the delay between server process start and
     * log directory creation by polling until files appear.
     */
    protected function tailNativeLogs(Server $server, WritesNativeLogs $handler): int
    {
        $baseDir = $handler->getNativeLogDirectory($server);
        $pattern = $handler->getNativeLogFilePattern();

        $this->info("Tailing native logs for server '{$server->name}' (ID: {$server->id})");
        $this->info("Watching: {$baseDir}");

        $logFiles = $this->discoverLogFiles($baseDir, $pattern);

        /** @var array<string, resource> $handles */
        $handles = [];
        /** @var array<string, string> $buffers */
        $buffers = [];

        while (true) {
            // Discover new log files if we haven't found any yet or new ones appeared.
            if ($handles === []) {
                $logFiles = $this->discoverLogFiles($baseDir, $pattern);

                foreach ($logFiles as $logFile) {
                    $handle = fopen($logFile, 'r');

                    if ($handle) {
                        $handles[$logFile] = $handle;
                        $buffers[$logFile] = '';
                        $this->info("Tailing: {$logFile}");
                    }
                }

                if ($handles === []) {
                    usleep(500_000);

                    continue;
                }
            }

            $hadData = false;

            foreach ($handles as $path => $handle) {
                if ($this->readAndDispatch($handle, $buffers[$path], $server->id, $path)) {
                    $hadData = true;
                }
            }

            if (! $hadData) {
                usleep(250_000);
            }
        }

        // @codeCoverageIgnoreStart
        foreach ($handles as $handle) {
            fclose($handle);
        }

        return self::SUCCESS;
        // @codeCoverageIgnoreEnd
    }

    /**
     * Find the latest timestamped log subdirectory and return all matching log files.
     *
     * @return list<string>
     */
    protected function discoverLogFiles(string $baseDir, string $filePattern): array
    {
        if (! is_dir($baseDir)) {
            return [];
        }

        // Find the latest timestamped subdirectory (e.g., logs_2026-03-11_11-54-13).
        $subdirs = glob($baseDir.'/logs_*', GLOB_ONLYDIR) ?: [];

        if ($subdirs === []) {
            return [];
        }

        sort($subdirs);
        $latestDir = end($subdirs);

        return glob($latestDir.'/'.$filePattern) ?: [];
    }

    /**
     * Read new data from a file handle, buffer partial lines, and dispatch complete lines.
     *
     * @return bool Whether any data was read.
     */
    protected function readAndDispatch(mixed $handle, string &$buffer, int $serverId, string $path): bool
    {
        $chunk = fread($handle, 8192);

        if ($chunk !== false && $chunk !== '') {
            $buffer .= $chunk;

            while (($newlinePos = strpos($buffer, "\n")) !== false) {
                $line = rtrim(substr($buffer, 0, $newlinePos), "\r");
                $buffer = substr($buffer, $newlinePos + 1);

                if ($line !== '') {
                    ServerLogOutput::dispatch($serverId, $line);
                }
            }

            return true;
        }

        clearstatcache(false, $path);
        $currentSize = filesize($path);
        $position = ftell($handle);

        if ($currentSize !== false && $position !== false && $currentSize < $position) {
            fseek($handle, 0);
            $buffer = '';
        } else {
            fseek($handle, ftell($handle));
        }

        return false;
    }
}
