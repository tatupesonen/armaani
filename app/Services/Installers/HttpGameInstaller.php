<?php

namespace App\Services\Installers;

use App\Contracts\DownloadsDirectly;
use App\Contracts\GameHandler;
use App\Contracts\GameServerInstaller;
use App\Models\GameInstall;
use App\Services\HttpDownloadService;
use Illuminate\Support\Facades\File;

class HttpGameInstaller implements GameServerInstaller
{
    public function __construct(
        protected HttpDownloadService $httpDownload,
    ) {}

    public function install(GameInstall $install, GameHandler $handler, callable $onOutput): ?string
    {
        assert($handler instanceof DownloadsDirectly);

        $installPath = $install->getInstallationPath();

        if (is_dir($installPath)) {
            $onOutput(0, 'Cleaning previous installation...');
            File::deleteDirectory($installPath);
        }

        $this->httpDownload->downloadAndExtract(
            url: $handler->getDownloadUrl($install->branch),
            destinationDir: $install->getInstallationPath(),
            stripComponents: $handler->getArchiveStripComponents(),
            onOutput: $onOutput,
        );

        return null;
    }
}
