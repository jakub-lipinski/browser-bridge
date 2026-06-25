<?php

namespace App\Http\Controllers\Dashboard;

use App\Enums\TabCommandStatus;
use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function destroy(Device $device): RedirectResponse
    {
        $device->update([
            'is_active' => false,
            'disconnected_at' => now(),
            'removal_reason' => 'disconnected_from_dashboard',
        ]);

        $device->sentTabCommands()->where('status', TabCommandStatus::Pending)->update(['status' => TabCommandStatus::Dismissed]);
        $device->incomingTabCommands()->where('status', TabCommandStatus::Pending)->update(['status' => TabCommandStatus::Dismissed]);

        $device->bookmarkSyncProfilesAsSource()->update(['is_active' => false]);
        $device->bookmarkSyncProfilesAsTarget()->update(['is_active' => false]);

        $device->delete();

        return redirect()->back()->with('status', 'Device disconnected. Existing synced data was kept.');
    }

    public function purge(Request $request, int $deviceId): RedirectResponse
    {
        $device = Device::withTrashed()->findOrFail($deviceId);

        $request->validate([
            'confirmation_text' => 'required|string|in:DELETE DEVICE DATA',
        ]);

        $device->bookmarkSnapshots()->delete();
        $device->normalizedBookmarks()->delete();
        $device->tabSnapshots()->delete();
        $device->historyItems()->delete();

        $device->sentTabCommands()->delete();
        $device->incomingTabCommands()->delete();

        $device->bookmarkSyncProfilesAsSource()->each(function ($profile) {
            $profile->runs()->delete();
            $profile->delete();
        });

        $device->bookmarkSyncProfilesAsTarget()->each(function ($profile) {
            $profile->runs()->delete();
            $profile->delete();
        });

        $device->bookmarkBackups()->delete();

        $device->forceDelete();

        return redirect()->back()->with('status', 'Device and related data permanently deleted.');
    }
}
