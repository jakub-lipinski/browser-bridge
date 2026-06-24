<?php

namespace App\Http\Controllers\Api;

use App\Enums\TabCommandStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Requests\SendTabCommandRequest;
use App\Http\Resources\TabCommandResource;
use App\Models\TabCommand;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TabCommandController extends Controller
{
    public function send(
        SendTabCommandRequest $request,
        DeviceResolver $deviceResolver,
        BrowserSyncService $syncService,
    ): TabCommandResource {
        $sourceDevice = $deviceResolver->required($request->string('source_device_uuid')->toString());
        $targetDevice = $deviceResolver->required($request->string('target_device_uuid')->toString());

        $command = $syncService->sendTabCommand(
            $sourceDevice,
            $targetDevice,
            $request->string('url')->toString(),
            $request->string('title')->trim()->toString() ?: null,
        );

        return new TabCommandResource($command->load('sourceDevice'));
    }

    public function incoming(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
        BrowserSyncService $syncService,
    ): AnonymousResourceCollection {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());

        return TabCommandResource::collection($syncService->incomingTabCommands($device));
    }

    public function opened(
        IncomingTabCommandsRequest $request,
        TabCommand $tabCommand,
        DeviceResolver $deviceResolver,
    ): TabCommandResource {
        return $this->mark($request, $tabCommand, $deviceResolver, TabCommandStatus::Opened);
    }

    public function dismissed(
        IncomingTabCommandsRequest $request,
        TabCommand $tabCommand,
        DeviceResolver $deviceResolver,
    ): TabCommandResource {
        return $this->mark($request, $tabCommand, $deviceResolver, TabCommandStatus::Dismissed);
    }

    private function mark(
        IncomingTabCommandsRequest $request,
        TabCommand $tabCommand,
        DeviceResolver $deviceResolver,
        TabCommandStatus $status,
    ): TabCommandResource {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());

        abort_unless($tabCommand->target_device_id === $device->id, 404);

        $tabCommand->update(['status' => $status]);

        return new TabCommandResource($tabCommand->refresh()->load('sourceDevice'));
    }
}
