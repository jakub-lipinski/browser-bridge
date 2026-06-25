<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRegisterRequest;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function register(DeviceRegisterRequest $request, BrowserSyncService $syncService): DeviceResource
    {
        $device = $syncService->registerDevice(
            $request->string('device_uuid')->trim()->toString() ?: null,
            $request->safe()->only(['name', 'browser', 'platform', 'capabilities']),
        );

        return new DeviceResource($device);
    }

    public function index(IncomingTabCommandsRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $deviceResolver->required($request->string('device_uuid')->toString());

        return DeviceResource::collection(
            Device::query()
                ->when($request->boolean('include_disconnected'), fn ($query) => $query->withTrashed())
                ->latest('last_seen_at')
                ->latest()
                ->get(),
        );
    }
}
