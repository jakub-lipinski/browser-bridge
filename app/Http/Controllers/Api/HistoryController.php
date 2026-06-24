<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\HistoryBatchRequest;
use App\Http\Requests\HistorySearchRequest;
use App\Http\Resources\HistoryItemResource;
use App\Models\HistoryItem;
use App\Services\BrowserSyncService;
use App\Services\DeviceResolver;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HistoryController extends Controller
{
    public function batch(
        HistoryBatchRequest $request,
        DeviceResolver $deviceResolver,
        BrowserSyncService $syncService,
    ): AnonymousResourceCollection {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $items = $syncService->storeHistoryBatch($device, $request->validated('items'));

        return HistoryItemResource::collection($items);
    }

    public function search(HistorySearchRequest $request, DeviceResolver $deviceResolver): AnonymousResourceCollection
    {
        $device = $deviceResolver->required($request->string('device_uuid')->toString());
        $query = $request->string('query')->trim()->toString();
        $limit = (int) ($request->validated('limit') ?? config('browserbridge.history_search_limit'));

        return HistoryItemResource::collection(
            HistoryItem::query()
                ->whereBelongsTo($device)
                ->when($query !== '', function ($historyQuery) use ($query): void {
                    $historyQuery->where(function ($innerQuery) use ($query): void {
                        $innerQuery
                            ->where('url', 'like', '%'.$query.'%')
                            ->orWhere('title', 'like', '%'.$query.'%');
                    });
                })
                ->latest('visited_at')
                ->limit($limit)
                ->get(),
        );
    }
}
