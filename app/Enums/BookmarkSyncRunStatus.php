<?php

namespace App\Enums;

enum BookmarkSyncRunStatus: string
{
    case Preview = 'preview';
    case Running = 'running';
    case Completed = 'completed';
    case Failed = 'failed';
    case Cancelled = 'cancelled';
}
