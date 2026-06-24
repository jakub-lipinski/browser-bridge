<?php

namespace App\Enums;

enum BookmarkSyncDirection: string
{
    case SourceToTarget = 'source_to_target';
    case TargetToSource = 'target_to_source';
    case TwoWay = 'two_way';
}
