<?php

namespace App\Console\Commands;

use App\Services\VersionCheckService;
use Illuminate\Console\Command;

class CheckVersionCommand extends Command
{
    /** @var string */
    protected $signature = 'app:version-check';

    /** @var string */
    protected $description = 'Check if a newer version of Armaani is available on GitHub';

    public function handle(VersionCheckService $service): int
    {
        $result = $service->check();

        if ($result['error']) {
            $this->components->warn("Could not check for updates: {$result['error']}");

            return self::SUCCESS;
        }

        if ($result['update_available']) {
            $this->newLine();
            $this->components->warn("A new version of Armaani is available: v{$result['latest']} (current: v{$result['current']})");
            $this->line('  https://github.com/tatupesonen/Armaani/releases/latest');
            $this->newLine();
        } else {
            $this->components->info("Armaani is up to date (v{$result['current']}).");
        }

        return self::SUCCESS;
    }
}
