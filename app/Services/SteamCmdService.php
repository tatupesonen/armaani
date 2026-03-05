<?php

namespace App\Services;

use App\Models\SteamAccount;
use Illuminate\Support\Facades\Process;
use RuntimeException;

class SteamCmdService
{
    /**
     * Build and run a SteamCMD command to install or update the Arma 3 server.
     */
    public function installServer(string $installDir): \Illuminate\Contracts\Process\ProcessResult
    {
        $args = $this->baseArgs($installDir);
        $args[] = '+app_update '.config('arma.server_app_id').' validate';
        $args[] = '+quit';

        return $this->run($args);
    }

    /**
     * Build and run a SteamCMD command to download a single workshop mod.
     */
    public function downloadMod(string $installDir, int $workshopId): \Illuminate\Contracts\Process\ProcessResult
    {
        $args = $this->baseArgs($installDir);
        $args[] = '+workshop_download_item '.config('arma.game_id').' '.$workshopId.' validate';
        $args[] = '+quit';

        return $this->run($args);
    }

    /**
     * Validate that the given Steam credentials work with SteamCMD.
     */
    public function validateCredentials(string $username, string $password): bool
    {
        $steamcmdPath = config('arma.steamcmd_path');

        $result = Process::timeout(60)->run([
            $steamcmdPath,
            '+login', $username, $password,
            '+quit',
        ]);

        return $result->successful();
    }

    /**
     * @param  list<string>  $args
     */
    protected function run(array $args): \Illuminate\Contracts\Process\ProcessResult
    {
        $steamcmdPath = config('arma.steamcmd_path');

        return Process::timeout(3600)
            ->run(array_merge([$steamcmdPath], $args));
    }

    /**
     * Build the common SteamCMD arguments (install dir + login).
     *
     * @return list<string>
     */
    protected function baseArgs(string $installDir): array
    {
        $account = SteamAccount::query()->latest()->first();

        if (! $account) {
            throw new RuntimeException('No Steam account configured. Please configure Steam credentials in Settings.');
        }

        return [
            '+force_install_dir', $installDir,
            '+login', $account->username, $account->password,
        ];
    }
}
