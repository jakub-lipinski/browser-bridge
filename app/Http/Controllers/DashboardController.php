<?php

namespace App\Http\Controllers;

use App\Enums\TabCommandStatus;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\NormalizedBookmark;
use App\Models\TabCommand;
use App\Models\TabSnapshot;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request): View
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
            ],
            'bookmarkQuery' => $bookmarkQuery,
            'browserBridgeBookmarks' => NormalizedBookmark::query()
                ->with('device')
                ->where('type', 'bookmark')
                ->when($bookmarkQuery !== '', function ($query) use ($bookmarkQuery): void {
                    $query->where(function ($innerQuery) use ($bookmarkQuery): void {
                        $innerQuery
                            ->where('title', 'like', '%'.$bookmarkQuery.'%')
                            ->orWhere('url', 'like', '%'.$bookmarkQuery.'%');
                    });
                })
                ->orderBy('device_id')
                ->orderBy('title')
                ->limit(50)
                ->get()
                ->groupBy(fn (NormalizedBookmark $bookmark): string => $bookmark->device?->name ?? 'Unknown device'),
            'latestHistoryItems' => HistoryItem::query()
                ->with('device')
                ->latest('visited_at')
                ->limit(10)
                ->get(),
            'pendingTabCommands' => TabCommand::query()
                ->with(['sourceDevice', 'targetDevice'])
                ->where('status', TabCommandStatus::Pending)
                ->latest()
                ->limit(10)
                ->get(),
        ]);
    }
}
