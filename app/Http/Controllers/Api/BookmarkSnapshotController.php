<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookmarkSearchRequest;
use App\Http\Requests\BookmarkSnapshotRequest;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Resources\BookmarkSnapshotResource;
use App\Http\Resources\NormalizedBookmarkResource;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\NormalizedBookmark;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

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

    public function bookmarks(BookmarkSearchRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $limit = (int) ($request->validated('limit') ?? 100);

        return NormalizedBookmarkResource::collection(
            NormalizedBookmark::query()
                ->with('device')
                ->where('type', 'bookmark')
                ->latest('updated_at')
                ->limit($limit)
                ->get(),
        );
    }

    public function search(BookmarkSearchRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $query = $request->string('query')->trim()->toString();
        $limit = (int) ($request->validated('limit') ?? 100);

        return NormalizedBookmarkResource::collection(
            NormalizedBookmark::query()
                ->with('device')
                ->where('type', 'bookmark')
                ->when($query !== '', function ($bookmarkQuery) use ($query): void {
                    $bookmarkQuery->where(function ($innerQuery) use ($query): void {
                        $innerQuery
                            ->where('title', 'like', '%'.$query.'%')
                            ->orWhere('url', 'like', '%'.$query.'%');
                    });
                })
                ->orderBy('device_id')
                ->orderBy('type')
                ->orderBy('title')
                ->limit($limit)
                ->get(),
        );
    }

    public function destroyForDevice(
        IncomingTabCommandsRequest $request,
        Device $device,
        DeviceResolver $deviceResolver,
    ): Response {
        $deviceResolver->required($request->string('device_uuid')->toString());

        $device->normalizedBookmarks()->delete();
        $device->bookmarkSnapshots()->delete();

        return response()->noContent();
    }
}
