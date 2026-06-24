<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookmarkSnapshotRequest;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Resources\BookmarkSnapshotResource;
use App\Models\BookmarkSnapshot;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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

    public function index(IncomingTabCommandsRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $deviceResolver->required($request->string('device_uuid')->toString());

        return BookmarkSnapshotResource::collection(
            BookmarkSnapshot::query()
                ->with('device')
                ->latest()
                ->limit(10)
                ->get(),
        );
    }
}
