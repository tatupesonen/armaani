<?php

namespace App\Contracts;

use App\Models\Server;

interface SupportsHeadlessClients
{
    /**
     * Build the launch command arguments for a headless client instance.
     *
     * @return array<int, string> The binary path as the first element, followed by arguments.
     */
    public function buildHeadlessClientCommand(Server $server, int $index): array;
}
