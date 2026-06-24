<?php

namespace App\Enums;

enum BookmarkSyncTargetScope: string
{
    case BrowserBridgeFolder = 'browserbridge_folder';
    case SelectedFolder = 'selected_folder';
    case EntireBookmarksRoot = 'entire_bookmarks_root';
}
