<?php

namespace App\Enums;

enum SyncStatus: string
{
    case Idle = 'idle';
    case Queued = 'queued';
    case Running = 'running';
    case Failed = 'failed';
}
