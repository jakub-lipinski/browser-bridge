<?php

namespace App\Http\Controllers\Api;

use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncRunStatus;
use App\Enums\BookmarkSyncTargetScope;
use App\Http\Controllers\Controller;
use App\Http\Requests\BookmarkSyncProfileRequest;
use App\Http\Requests\BookmarkSyncRunRequest;
use App\Http\Requests\IncomingTabCommandsRequest;
use App\Http\Resources\BookmarkSyncProfileResource;
use App\Http\Resources\BookmarkSyncRunResource;
use App\Models\BookmarkBackup;
use App\Models\BookmarkSyncProfile;
use App\Models\BookmarkSyncRun;
use App\Services\BookmarkSyncPlanner;
use App\Services\DeviceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BookmarkSyncProfileController extends Controller
{
    public function index(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
    ): AnonymousResourceCollection {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());

        return BookmarkSyncProfileResource::collection(
            BookmarkSyncProfile::query()
                ->with(['sourceDevice', 'targetDevice', 'latestRun'])
                ->where(function ($query) use ($device): void {
                    $query
                        ->where('source_device_id', $device->id)
                        ->orWhere('target_device_id', $device->id);
                })
                ->latest()
                ->get(),
        );
    }

    public function store(
        BookmarkSyncProfileRequest $request,
        DeviceResolver $deviceResolver,
    ): BookmarkSyncProfileResource {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $attributes = $request->safe()->except('device_uuid');
        $attributes = $this->withNextRunAt($attributes);

        $profile = BookmarkSyncProfile::query()->create($attributes);

        return new BookmarkSyncProfileResource($profile->load(['sourceDevice', 'targetDevice', 'latestRun']));
    }

    public function update(
        BookmarkSyncProfileRequest $request,
        DeviceResolver $deviceResolver,
        BookmarkSyncProfile $bookmarkSyncProfile,
    ): BookmarkSyncProfileResource {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $attributes = $request->safe()->except('device_uuid');
        $attributes = $this->withNextRunAt($attributes);

        $bookmarkSyncProfile->update($attributes);

        return new BookmarkSyncProfileResource($bookmarkSyncProfile->load(['sourceDevice', 'targetDevice', 'latestRun']));
    }

    public function destroy(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
        BookmarkSyncProfile $bookmarkSyncProfile,
    ): Response {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $bookmarkSyncProfile->delete();

        return response()->noContent();
    }

    public function preview(
        BookmarkSyncRunRequest $request,
        DeviceResolver $deviceResolver,
        BookmarkSyncProfile $bookmarkSyncProfile,
        BookmarkSyncPlanner $planner,
    ): JsonResponse {
        $deviceResolver->required($request->string('device_uuid')->toString());
        $preview = $planner->preview($bookmarkSyncProfile);
        $run = $bookmarkSyncProfile->runs()->create([
            'source_device_id' => $bookmarkSyncProfile->source_device_id,
            'target_device_id' => $bookmarkSyncProfile->target_device_id,
            'mode' => $bookmarkSyncProfile->mode,
            'status' => BookmarkSyncRunStatus::Preview,
            ...$planner->countsFromPreview($preview),
            'preview_json' => $preview,
        ]);

        return response()->json([
            'success' => true,
            'data' => [
                ...$preview,
                'run_id' => $run->id,
            ],
        ]);
    }

    public function run(
        BookmarkSyncRunRequest $request,
        DeviceResolver $deviceResolver,
        BookmarkSyncProfile $bookmarkSyncProfile,
        BookmarkSyncPlanner $planner,
    ): BookmarkSyncRunResource {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $this->ensureRunIsAllowed($request, $bookmarkSyncProfile);

        $previewRun = $bookmarkSyncProfile
            ->runs()
            ->where('status', BookmarkSyncRunStatus::Preview->value)
            ->latest()
            ->first();

        if (! $previewRun instanceof BookmarkSyncRun) {
            throw ValidationException::withMessages([
                'preview' => 'Preview this bookmark sync profile before running it.',
            ]);
        }

        $run = DB::transaction(function () use ($request, $device, $bookmarkSyncProfile, $planner, $previewRun): BookmarkSyncRun {
            $preview = $previewRun->preview_json ?? $planner->preview($bookmarkSyncProfile);
            $validated = $request->validated();
            $result = [
                'operation_log' => $validated['operation_log'] ?? [],
                'extension_result' => $validated['result'] ?? [],
                'native_apply' => 'completed_by_extension',
            ];

            $run = $bookmarkSyncProfile->runs()->create([
                'source_device_id' => $bookmarkSyncProfile->source_device_id,
                'target_device_id' => $bookmarkSyncProfile->target_device_id,
                'mode' => $bookmarkSyncProfile->mode,
                'status' => BookmarkSyncRunStatus::Completed,
                ...$planner->countsFromPreview($preview),
                'preview_json' => $preview,
                'result_json' => $result,
            ]);

            if ($bookmarkSyncProfile->mode === BookmarkSyncMode::Mirror) {
                $this->createMirrorBackup($request, $run, $device->id);
            }

            $bookmarkSyncProfile->update([
                'last_run_at' => now(),
                'next_run_at' => $this->nextRunAt($bookmarkSyncProfile),
            ]);

            return $run;
        });

        return new BookmarkSyncRunResource($run->load(['profile', 'sourceDevice', 'targetDevice']));
    }

    public function runs(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
    ): AnonymousResourceCollection {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());

        return BookmarkSyncRunResource::collection(
            BookmarkSyncRun::query()
                ->with(['profile', 'sourceDevice', 'targetDevice'])
                ->where(function ($query) use ($device): void {
                    $query
                        ->where('source_device_id', $device->id)
                        ->orWhere('target_device_id', $device->id);
                })
                ->latest()
                ->limit(25)
                ->get(),
        );
    }

    public function showRun(
        IncomingTabCommandsRequest $request,
        DeviceResolver $deviceResolver,
        BookmarkSyncRun $bookmarkSyncRun,
    ): BookmarkSyncRunResource {
        $deviceResolver->required($request->string('device_uuid')->toString());

        return new BookmarkSyncRunResource($bookmarkSyncRun->load(['profile', 'sourceDevice', 'targetDevice']));
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function withNextRunAt(array $attributes): array
    {
        if (
            ($attributes['auto_sync_enabled'] ?? false)
            && filled($attributes['auto_sync_interval_minutes'] ?? null)
        ) {
            $attributes['next_run_at'] = Carbon::now()->addMinutes((int) $attributes['auto_sync_interval_minutes']);
        } else {
            $attributes['next_run_at'] = null;
        }

        return $attributes;
    }

    private function ensureRunIsAllowed(BookmarkSyncRunRequest $request, BookmarkSyncProfile $profile): void
    {
        if ($profile->targetDevice?->browser === 'safari') {
            throw ValidationException::withMessages([
                'target_device_id' => 'Native Safari bookmark writing is not available in this Safari version.',
            ]);
        }

        if (
            $profile->mode === BookmarkSyncMode::Mirror
            && $profile->target_scope === BookmarkSyncTargetScope::EntireBookmarksRoot
        ) {
            throw ValidationException::withMessages([
                'target_scope' => 'Full-root Mirror is disabled in this build. Choose a BrowserBridge-managed folder or selected folder.',
            ]);
        }

        if ($profile->mode !== BookmarkSyncMode::Mirror) {
            return;
        }

        if (! $request->boolean('confirm_mirror')) {
            throw ValidationException::withMessages([
                'confirm_mirror' => 'Mirror requires explicit confirmation before it can run.',
            ]);
        }

        $validated = $request->validated();

        if (! $request->boolean('backup_created') && ! is_array($validated['backup_payload'] ?? null)) {
            throw ValidationException::withMessages([
                'backup_created' => 'Mirror requires a bookmark backup before it can run.',
            ]);
        }
    }

    private function createMirrorBackup(BookmarkSyncRunRequest $request, BookmarkSyncRun $run, int $deviceId): void
    {
        $validated = $request->validated();

        if (($validated['backup_created'] ?? false) && ! isset($validated['backup_payload'])) {
            return;
        }

        BookmarkBackup::query()->create([
            'device_id' => $deviceId,
            'sync_run_id' => $run->id,
            'payload_json' => $validated['backup_payload'] ?? [
                'items' => [],
                'note' => 'Backup was reported by the extension before Mirror ran.',
            ],
            'encrypted_payload' => null,
            'created_at' => now(),
        ]);
    }

    private function nextRunAt(BookmarkSyncProfile $profile): ?Carbon
    {
        if (! $profile->auto_sync_enabled || ! $profile->auto_sync_interval_minutes) {
            return null;
        }

        return now()->addMinutes($profile->auto_sync_interval_minutes);
    }
}
