<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Requests\TabSnapshotRequest;
use App\Http\Resources\TabSnapshotResource;
use App\Models\TabSnapshot;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TabSnapshotController extends Controller
{
    public function store(
        TabSnapshotRequest $request,
        DeviceResolver $deviceResolver,
        BrowserSyncService $syncService,
    ): TabSnapshotResource {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $snapshot = $syncService->storeTabSnapshot($device, $request->safe()->only(['tabs']));

        return new TabSnapshotResource($snapshot);
    }

    public function index(IncomingTabCommandsRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $deviceResolver->required($request->string('device_uuid')->toString());

        return TabSnapshotResource::collection(
            TabSnapshot::query()
                ->with('device')
                ->latest()
                ->limit(20)
                ->get(),
        );
    }
}
