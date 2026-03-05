<?php

namespace App\Enums;

enum InstallationStatus: string
{
    case Queued = 'queued';
    case Installing = 'installing';
    case Installed = 'installed';
    case Failed = 'failed';
}
