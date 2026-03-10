<?php

namespace App\Console\Commands;

use App\Providers\GameServiceProvider;
use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class GameHandlersClearCommand extends Command
{
    protected $signature = 'game-handlers:clear';

    protected $description = 'Clear the cached game handler manifest';

    public function handle(Filesystem $files): void
    {
        $files->delete(GameServiceProvider::cachePath());

        $this->components->info('Game handler cache cleared successfully.');
    }
}
