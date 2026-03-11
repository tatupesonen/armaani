<?php

namespace Tests\Concerns;

use App\Services\Steam\SteamWorkshopService;
use Mockery;
use Mockery\MockInterface;

trait MocksSteamServices
{
    protected function mockWorkshopService(): MockInterface
    {
        $workshop = Mockery::mock(SteamWorkshopService::class);
        $workshop->shouldReceive('syncMetadata')->andReturnNull();
        $workshop->shouldReceive('syncMetadataForMany')->andReturnNull();

        return $workshop;
    }
}
