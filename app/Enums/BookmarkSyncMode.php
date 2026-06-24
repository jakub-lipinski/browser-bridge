<?php

namespace App\Enums;

enum BookmarkSyncMode: string
{
    case SafeFolder = 'safe_folder';
    case Merge = 'merge';
    case Mirror = 'mirror';
}
