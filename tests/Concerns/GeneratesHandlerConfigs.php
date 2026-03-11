<?php

namespace Tests\Concerns;

use App\Models\Server;

trait GeneratesHandlerConfigs
{
    /**
     * Generate config files for a server and return the parsed JSON content.
     *
     * @return array<string, mixed>
     */
    protected function generateAndReadJsonConfig(Server $server, string $filename): array
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/'.$filename;
        $this->assertFileExists($configPath);

        return json_decode(file_get_contents($configPath), true);
    }

    /**
     * Generate config files for a server and return the raw file content.
     */
    protected function generateAndReadRawConfig(Server $server, string $filename): string
    {
        $profilesPath = $server->getProfilesPath();
        @mkdir($profilesPath, 0755, true);

        $this->handler->generateConfigFiles($server);

        $configPath = $profilesPath.'/'.$filename;
        $this->assertFileExists($configPath);

        return file_get_contents($configPath);
    }
}
