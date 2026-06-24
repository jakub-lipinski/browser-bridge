<?php

namespace App\Http\Controllers;

use App\Enums\TabCommandStatus;
use App\Models\BookmarkSnapshot;
use App\Models\Device;
use App\Models\HistoryItem;
use App\Models\TabCommand;
use App\Models\TabSnapshot;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View
    {
        $devices = Device::query()
            ->with(['latestBookmarkSnapshot', 'latestTabSnapshot'])
            ->withCount([
                'historyItems',
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
                'tabSnapshots' => TabSnapshot::query()->count(),
                'historyItems' => HistoryItem::query()->count(),
                'tabCommands' => TabCommand::query()->count(),
            ],
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
