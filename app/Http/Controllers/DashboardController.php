<?php

namespace App\Http\Controllers;

use App\Enums\TabCommandStatus;
use App\Models\Device;
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
            'pendingCommandCount' => $devices->sum('pending_tab_commands_count'),
            'historyItemCount' => $devices->sum('history_items_count'),
        ]);
    }
}
