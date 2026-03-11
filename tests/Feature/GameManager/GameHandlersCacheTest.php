<?php

namespace Tests\Feature\GameManager;

use App\Attributes\Beta;
use App\Contracts\GameHandler;
use App\GameManager;
use App\Providers\GameServiceProvider;
use Tests\TestCase;

class GameHandlersCacheTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure no stale cache from previous tests.
        @unlink(GameServiceProvider::cachePath());
    }

    protected function tearDown(): void
    {
        // Always clean up the cache file after each test.
        @unlink(GameServiceProvider::cachePath());

        parent::tearDown();
    }

    public function test_cache_command_writes_manifest_file(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:cache')
            ->expectsOutputToContain('Game handlers cached successfully')
            ->assertExitCode(0);

        $this->assertFileExists(GameServiceProvider::cachePath());
    }

    public function test_cache_manifest_contains_all_handlers(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);

        $manifest = require GameServiceProvider::cachePath();
        $discovered = GameServiceProvider::discoverHandlers();

        $this->assertSame(array_keys($discovered), array_keys($manifest));

        foreach ($discovered as $key => $class) {
            $this->assertSame($class, $manifest[$key], "Cached class for '{$key}' must match discovered class");
        }
    }

    public function test_clear_command_removes_manifest_file(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);
        $this->assertFileExists(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:clear')
            ->expectsOutputToContain('Game handler cache cleared successfully')
            ->assertExitCode(0);

        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());
    }

    public function test_clear_command_succeeds_when_no_cache_exists(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $this->artisan('game-handlers:clear')->assertExitCode(0);
    }

    public function test_cache_command_clears_stale_cache_before_writing(): void
    {
        // Write a stale manifest.
        file_put_contents(
            GameServiceProvider::cachePath(),
            '<?php return ["stale" => "StaleHandler"];'.PHP_EOL,
        );

        $this->artisan('game-handlers:cache')->assertExitCode(0);

        $manifest = require GameServiceProvider::cachePath();

        $this->assertArrayNotHasKey('stale', $manifest);
        $this->assertArrayHasKey('arma3', $manifest);
    }

    public function test_discover_handlers_returns_correct_map(): void
    {
        $handlers = GameServiceProvider::discoverHandlers();

        $this->assertNotEmpty($handlers);

        foreach ($handlers as $key => $class) {
            $this->assertIsString($key);
            $this->assertTrue(
                is_subclass_of($class, GameHandler::class),
                "{$class} should implement GameHandler",
            );
        }
    }

    public function test_handler_map_reads_from_cache_when_available(): void
    {
        // Write a custom manifest to prove it's read from cache, not discovered.
        file_put_contents(
            GameServiceProvider::cachePath(),
            '<?php return ["cached_key" => "App\\\\GameHandlers\\\\Arma3Handler"];'.PHP_EOL,
        );

        $map = GameServiceProvider::handlerMap();

        $this->assertArrayHasKey('cached_key', $map);
        $this->assertSame('App\\GameHandlers\\Arma3Handler', $map['cached_key']);
    }

    public function test_handler_map_falls_back_to_discovery_without_cache(): void
    {
        $this->assertFileDoesNotExist(GameServiceProvider::cachePath());

        $map = GameServiceProvider::handlerMap();
        $discovered = GameServiceProvider::discoverHandlers();

        $this->assertSame(array_keys($discovered), array_keys($map));
    }

    public function test_game_manager_works_with_cached_manifest(): void
    {
        $this->artisan('game-handlers:cache')->assertExitCode(0);

        // Rebuild the singleton so it reads from the cache.
        $this->app->forgetInstance(GameManager::class);
        $manager = $this->app->make(GameManager::class);

        $discovered = GameServiceProvider::discoverHandlers();

        foreach ($discovered as $key => $class) {
            $this->assertInstanceOf($class, $manager->driver($key));
        }
    }

    public function test_optimize_command_caches_game_handlers(): void
    {
        @unlink(GameServiceProvider::cachePath());

        $this->artisan('optimize')->assertExitCode(0);

        $this->assertFileExists(GameServiceProvider::cachePath());

        // Clean up other optimize artifacts.
        $this->artisan('optimize:clear');
    }

    // ---------------------------------------------------------------
    // #[Beta] attribute
    // ---------------------------------------------------------------

    public function test_beta_handlers_are_included_in_non_production(): void
    {
        // Test environment is 'testing', not 'production'.
        $this->assertFalse($this->app->isProduction());

        $handlers = GameServiceProvider::discoverHandlers();
        $betaDrivers = $this->getBetaDriverKeys($handlers);

        $this->assertNotEmpty($betaDrivers, 'At least one handler should be marked #[Beta]');

        foreach ($betaDrivers as $driver) {
            $this->assertArrayHasKey($driver, $handlers, "#[Beta] handler '{$driver}' should be included outside production");
        }
    }

    public function test_beta_handlers_are_excluded_in_production(): void
    {
        // Temporarily switch to production for both $this->app and app().
        $this->app['env'] = 'production';
        app()['env'] = 'production';
        $this->assertTrue(app()->isProduction());

        $handlers = GameServiceProvider::discoverHandlers();

        // Discover which classes have #[Beta] by scanning the directory directly.
        $allClasses = $this->getAllHandlerClasses();
        $betaClasses = array_filter($allClasses, function (string $class) {
            return (new \ReflectionClass($class))->getAttributes(Beta::class) !== [];
        });

        $this->assertNotEmpty($betaClasses, 'At least one handler class should have #[Beta]');

        foreach ($betaClasses as $class) {
            $this->assertNotContains(
                $class,
                $handlers,
                "#[Beta] class {$class} should be excluded in production",
            );
        }

        // Non-beta handlers should still be present.
        $nonBetaClasses = array_diff($allClasses, $betaClasses);
        foreach ($nonBetaClasses as $class) {
            $this->assertContains(
                $class,
                $handlers,
                "Non-beta class {$class} should still be included in production",
            );
        }
    }

    /**
     * Get the driver keys of all #[Beta] handlers from a discovered handler map.
     *
     * @param  array<string, class-string<GameHandler>>  $handlers
     * @return list<string>
     */
    private function getBetaDriverKeys(array $handlers): array
    {
        $betaDrivers = [];

        foreach ($handlers as $key => $class) {
            if ((new \ReflectionClass($class))->getAttributes(Beta::class) !== []) {
                $betaDrivers[] = $key;
            }
        }

        return $betaDrivers;
    }

    /**
     * Scan the GameHandlers directory for all concrete handler FQCNs.
     *
     * @return list<class-string<GameHandler>>
     */
    private function getAllHandlerClasses(): array
    {
        $classes = [];

        foreach (glob(app_path('GameHandlers/*.php')) ?: [] as $file) {
            /** @var class-string $class */
            $class = 'App\\GameHandlers\\'.pathinfo($file, PATHINFO_FILENAME);

            $reflection = new \ReflectionClass($class);

            if (! class_exists($class) || ! is_subclass_of($class, GameHandler::class) || $reflection->isAbstract()) {
                continue;
            }

            $classes[] = $class;
        }

        return $classes;
    }
}
