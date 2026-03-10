<?php

namespace App\Providers;

use App\Contracts\GameHandler;
use App\GameManager;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\ServiceProvider;

class GameServiceProvider extends ServiceProvider
{
    /**
     * Register the GameManager singleton.
     *
     * Handler registration is deferred into the singleton closure so no
     * filesystem scanning or class instantiation happens unless the
     * GameManager is actually resolved from the container.
     */
    public function register(): void
    {
        $this->app->singleton(GameManager::class, function (Container $app) {
            $manager = new GameManager($app);

            foreach (static::handlerMap() as $key => $class) {
                $manager->extend($key, fn () => $app->make($class));
            }

            return $manager;
        });
    }

    /**
     * Bootstrap services.
     *
     * Registers the game-handlers:cache and game-handlers:clear commands
     * with Laravel's optimize pipeline so they run on `php artisan optimize`.
     */
    public function boot(): void
    {
        $this->optimizes(
            optimize: 'game-handlers:cache',
            clear: 'game-handlers:clear',
        );
    }

    /**
     * Get the handler map, from cache if available.
     *
     * @return array<string, class-string<GameHandler>>
     */
    public static function handlerMap(): array
    {
        $cached = static::cachePath();

        if (file_exists($cached)) {
            return require $cached;
        }

        return static::discoverHandlers();
    }

    /**
     * Discover all game handler classes by scanning the GameHandlers directory.
     *
     * Each handler is resolved from the container to read its driver key via value().
     * The returned map is [driverKey => FQCN].
     *
     * @return array<string, class-string<GameHandler>>
     */
    public static function discoverHandlers(): array
    {
        $handlers = [];

        foreach (glob(app_path('GameHandlers/*.php')) ?: [] as $file) {
            /** @var class-string $class */
            $class = 'App\\GameHandlers\\'.pathinfo($file, PATHINFO_FILENAME);

            if (! class_exists($class) || ! is_subclass_of($class, GameHandler::class)) {
                continue;
            }

            /** @var GameHandler $handler */
            $handler = app()->make($class);
            $handlers[$handler->value()] = $class;
        }

        return $handlers;
    }

    /**
     * Get the path to the cached game handlers manifest.
     */
    public static function cachePath(): string
    {
        return app()->bootstrapPath('cache/game-handlers.php');
    }
}
