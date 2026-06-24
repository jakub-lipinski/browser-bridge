<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeviceRegisterRequest;
use App\Http\Resources\DeviceResource;
use App\Models\Device;
use App\Services\BrowserSyncService;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DeviceController extends Controller
{
    public function register(DeviceRegisterRequest $request, BrowserSyncService $syncService): DeviceResource
    {
        $device = $syncService->registerDevice(
            $request->string('device_uuid')->trim()->toString() ?: null,
            $request->safe()->only(['name', 'browser', 'platform']),
        );

        return new DeviceResource($device);
    }

    public function index(): AnonymousResourceCollection
    {
        return DeviceResource::collection(
            Device::query()
                ->latest('last_seen_at')
                ->latest()
                ->get(),
        );
    }
}
