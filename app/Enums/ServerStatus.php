<?php

namespace App\Enums;

enum ServerStatus: string
{
    case Stopped = 'stopped';
    case Starting = 'starting';
    case Booting = 'booting';
    case Running = 'running';
    case Stopping = 'stopping';
}
