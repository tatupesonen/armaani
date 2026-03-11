<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\File;

/**
 * Provides temporary directory management for tests that need filesystem paths.
 *
 * Usage:
 *   $this->setUpTestPaths(['servers', 'games', 'mods']);
 *   // Sets config('arma.servers_base_path'), config('arma.games_base_path'), etc.
 *   // Access via $this->testPath('servers')
 */
trait UsesTestPaths
{
    /** @var array<string, string> */
    protected array $testPaths = [];

    protected ?string $originalStoragePath = null;

    /**
     * Create temp directories and bind them to arma config keys.
     *
     * @param  list<string>  $keys  Path types (e.g. 'servers', 'games', 'mods', 'missions')
     */
    protected function setUpTestPaths(array $keys): void
    {
        foreach ($keys as $key) {
            $path = sys_get_temp_dir().'/armaani_test_'.$key.'_'.uniqid();
            $this->testPaths[$key] = $path;
            config(["arma.{$key}_base_path" => $path]);
        }
    }

    /**
     * Override app storage path with a temp directory.
     */
    protected function setUpTestStoragePath(): void
    {
        $this->originalStoragePath = app()->storagePath();
        $storagePath = sys_get_temp_dir().'/armaani_test_storage_'.uniqid();
        @mkdir($storagePath.'/app', 0755, true);
        $this->testPaths['storage'] = $storagePath;
        app()->useStoragePath($storagePath);
    }

    /**
     * Get a test path by key.
     */
    protected function testPath(string $key): string
    {
        return $this->testPaths[$key]
            ?? throw new \RuntimeException("Test path '{$key}' not set up. Call setUpTestPaths(['{$key}']).");
    }

    /**
     * Clean up all temp directories and restore storage path.
     */
    protected function tearDownTestPaths(): void
    {
        foreach ($this->testPaths as $path) {
            File::deleteDirectory($path);
        }
        $this->testPaths = [];

        if ($this->originalStoragePath !== null) {
            app()->useStoragePath($this->originalStoragePath);
            $this->originalStoragePath = null;
        }
    }
}
