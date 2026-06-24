<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookmarkSnapshotRequest;
use App\Http\Resources\BookmarkSnapshotResource;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;

class BookmarkSnapshotController extends Controller
{
    public function store(
        BookmarkSnapshotRequest $request,
        DeviceResolver $deviceResolver,
        BrowserSyncService $syncService,
    ): BookmarkSnapshotResource {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $snapshot = $syncService->storeBookmarkSnapshot($device, $request->safe()->only(['items']));

        return new BookmarkSnapshotResource($snapshot);
    }
}
