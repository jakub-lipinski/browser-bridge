<?php

namespace App\Http\Controllers;

use App\Models\HistoryItem;
use Illuminate\Http\RedirectResponse;

class DashboardHistoryController extends Controller
{
    public function __invoke(): RedirectResponse
    {
        HistoryItem::query()->delete();

        return redirect()
            ->route('dashboard')
            ->with('status', 'Synced BrowserBridge history was deleted.');
    }
}
