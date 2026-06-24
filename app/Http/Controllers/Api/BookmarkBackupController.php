<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BookmarkBackupRequest;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Resources\BookmarkBackupResource;
use App\Http\Resources\NormalizedBookmarkResource;
use App\Models\BookmarkBackup;
use App\Models\NormalizedBookmark;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookmarkBackupController extends Controller
{
    public function store(
        BookmarkBackupRequest $request,
        DeviceResolver $deviceResolver,
    ): BookmarkBackupResource {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $validated = $request->validated();

        $backup = BookmarkBackup::query()->create([
            'device_id' => $device->id,
            'sync_run_id' => $validated['sync_run_id'] ?? null,
            'payload_json' => $validated['payload'] ?? null,
            'encrypted_payload' => $validated['encrypted_payload'] ?? null,
            'created_at' => now(),
        ]);

        return new BookmarkBackupResource($backup->load('device'));
    }

    public function export(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
    ): AnonymousResourceCollection {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());

        return NormalizedBookmarkResource::collection(
            NormalizedBookmark::query()
                ->with('device')
                ->where('device_id', $device->id)
                ->where('type', 'bookmark')
                ->orderBy('title')
                ->get(),
        );
    }
}
