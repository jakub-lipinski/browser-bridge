<?php

namespace App\Http\Controllers;

use App\Enums\TabCommandStatus;
use App\Http\Resources\HistoryItemResource;
use App\Http\Resources\NormalizedBookmarkResource;
use App\Models\BookmarkSnapshot;
use App\Models\BookmarkSyncProfile;
use App\Models\BookmarkSyncRun;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\NormalizedBookmark;
use App\Models\TabCommand;
use App\Models\TabSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
    {
        return $this->index($request);
    }

    public function index(Request $request): View
    {
        $bookmarkQuery = $request->string('bookmark_query')->trim()->toString();
        $devices = Device::query()
            ->with(['latestBookmarkSnapshot', 'latestTabSnapshot'])
            ->withCount([
                'historyItems',
                'normalizedBookmarks',
                'incomingTabCommands as pending_tab_commands_count' => fn ($query) => $query->where('status', TabCommandStatus::Pending),
            ])
            ->latest('last_seen_at')
            ->latest()
            ->get();

        return view('dashboard', [
            'devices' => $devices,
            'storageCounts' => [
                'devices' => Device::query()->count(),
                'bookmarkSnapshots' => BookmarkSnapshot::query()->count(),
                'normalizedBookmarks' => NormalizedBookmark::query()->count(),
                'tabSnapshots' => TabSnapshot::query()->count(),
                'historyItems' => HistoryItem::query()->count(),
                'tabCommands' => TabCommand::query()->count(),
                'bookmarkSyncProfiles' => BookmarkSyncProfile::query()->count(),
                'bookmarkSyncRuns' => BookmarkSyncRun::query()->count(),
            ],
            'bookmarkQuery' => $bookmarkQuery,
            'browserBridgeBookmarks' => $this->bookmarkQuery($bookmarkQuery)
                ->limit(12)
                ->get()
                ->groupBy(fn (NormalizedBookmark $bookmark): string => $bookmark->device?->name ?? 'Unknown device'),
            'bookmarkTotal' => $this->bookmarkQuery($bookmarkQuery)->count(),
            'latestHistoryItems' => HistoryItem::query()
                ->with('device')
                ->latest('visited_at')
                ->limit(10)
                ->get(),
            'historyTotal' => HistoryItem::query()->count(),
            'latestTabSnapshots' => TabSnapshot::query()
                ->with('device')
                ->latest()
                ->limit(12)
                ->get()
                ->unique('device_id'),
            'tabCommands' => TabCommand::query()
                ->with(['sourceDevice', 'targetDevice'])
                ->latest()
                ->limit(12)
                ->get(),
            'bookmarkSyncProfiles' => BookmarkSyncProfile::query()
                ->with(['sourceDevice', 'targetDevice', 'latestRun'])
                ->latest()
                ->limit(8)
                ->get(),
            'bookmarkSyncRuns' => BookmarkSyncRun::query()
                ->with(['profile', 'sourceDevice', 'targetDevice'])
                ->latest()
                ->limit(8)
                ->get(),
        ]);
    }

    public function bookmarks(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $offset = (int) ($validated['offset'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 12);
        $bookmarkQuery = $this->bookmarkQuery($query);
        $total = (clone $bookmarkQuery)->count();
        $items = $bookmarkQuery
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => NormalizedBookmarkResource::collection($items)->resolve($request),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $items->count()) < $total,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'query' => ['nullable', 'string', 'max:200'],
            'offset' => ['nullable', 'integer', 'min:0'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $query = trim((string) ($validated['query'] ?? ''));
        $offset = (int) ($validated['offset'] ?? 0);
        $limit = (int) ($validated['limit'] ?? 10);
        $historyQuery = $this->historyQuery($query);
        $total = (clone $historyQuery)->count();
        $items = $historyQuery
            ->offset($offset)
            ->limit($limit)
            ->get();

        return response()->json([
            'data' => HistoryItemResource::collection($items)->resolve($request),
            'total' => $total,
            'offset' => $offset,
            'limit' => $limit,
            'has_more' => ($offset + $items->count()) < $total,
        ]);
    }

    /**
     * @return Builder<NormalizedBookmark>
     */
    private function bookmarkQuery(string $query): Builder
    {
        return NormalizedBookmark::query()
            ->with('device')
            ->where('type', 'bookmark')
            ->when($query !== '', function (Builder $bookmarkQuery) use ($query): void {
                $bookmarkQuery->where(function (Builder $innerQuery) use ($query): void {
                    $innerQuery
                        ->where('title', 'like', '%'.$query.'%')
                        ->orWhere('url', 'like', '%'.$query.'%');
                });
            })
            ->latest('updated_at')
            ->latest();
    }

    /**
     * @return Builder<HistoryItem>
     */
    private function historyQuery(string $query): Builder
    {
        return HistoryItem::query()
            ->with('device')
            ->when($query !== '', function (Builder $historyQuery) use ($query): void {
                $historyQuery->where(function (Builder $innerQuery) use ($query): void {
                    $innerQuery
                        ->where('title', 'like', '%'.$query.'%')
                        ->orWhere('url', 'like', '%'.$query.'%');
                });
            })
            ->latest('visited_at')
            ->latest();
    }
}
