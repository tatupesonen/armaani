<?php

namespace App\Console\Commands;

use App\Providers\GameServiceProvider;
use Illuminate\Console\Command;

class GameHandlersCacheCommand extends Command
{
    protected $signature = 'game-handlers:cache';

    protected $description = 'Discover and cache the game handler manifest';

    public function handle(): void
    {
        $this->callSilent('game-handlers:clear');

        $handlers = GameServiceProvider::discoverHandlers();

        file_put_contents(
            GameServiceProvider::cachePath(),
            '<?php return '.var_export($handlers, true).';'.PHP_EOL,
        );

        $this->components->info(sprintf(
            'Game handlers cached successfully. [%d handler(s)]',
            count($handlers),
        ));
    }
}
