<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['uuid', 'name', 'browser', 'platform', 'capabilities_json', 'last_seen_at'])]
class Device extends Model
{
    /** @use HasFactory<DeviceFactory> */
    use HasFactory;

    public function bookmarkSnapshots(): HasMany
    {
        return $this->hasMany(BookmarkSnapshot::class);
    }

    public function normalizedBookmarks(): HasMany
    {
        return $this->hasMany(NormalizedBookmark::class);
    }

    public function tabSnapshots(): HasMany
    {
        return $this->hasMany(TabSnapshot::class);
    }

    public function historyItems(): HasMany
    {
        return $this->hasMany(HistoryItem::class);
    }

    public function bookmarkSyncProfilesAsSource(): HasMany
    {
        return $this->hasMany(BookmarkSyncProfile::class, 'source_device_id');
    }

    public function bookmarkSyncProfilesAsTarget(): HasMany
    {
        return $this->hasMany(BookmarkSyncProfile::class, 'target_device_id');
    }

    public function bookmarkBackups(): HasMany
    {
        return $this->hasMany(BookmarkBackup::class);
    }

    public function latestBookmarkSnapshot(): HasOne
    {
        return $this->hasOne(BookmarkSnapshot::class)->latestOfMany();
    }

    public function latestTabSnapshot(): HasOne
    {
        return $this->hasOne(TabSnapshot::class)->latestOfMany();
    }

    public function sentTabCommands(): HasMany
    {
        return $this->hasMany(TabCommand::class, 'source_device_id');
    }

    public function incomingTabCommands(): HasMany
    {
        return $this->hasMany(TabCommand::class, 'target_device_id');
    }

    /**
     * @return array<string, mixed>
     */
    public function capabilities(): array
    {
        $defaults = match ($this->browser) {
            'chrome' => [
                'bookmarks_read' => true,
                'history_read' => true,
                'tabs_read' => true,
                'tab_commands' => true,
                'native_bookmark_write' => true,
                'reliable_background_sync' => true,
            ],
            'safari' => [
                'bookmarks_read' => false,
                'history_read' => false,
                'tabs_read' => true,
                'tab_commands' => true,
                'native_bookmark_write' => false,
                'reliable_background_sync' => false,
            ],
            default => [
                'bookmarks_read' => false,
                'history_read' => false,
                'tabs_read' => false,
                'tab_commands' => false,
                'native_bookmark_write' => false,
                'reliable_background_sync' => false,
            ],
        };

        $runtime = $this->capabilities_json ?? [];

        if ($runtime === []) {
            return $defaults;
        }

        return [
            ...$defaults,
            'bookmarks_read' => (bool) data_get($runtime, 'canReadNativeBookmarks', $defaults['bookmarks_read']),
            'history_read' => (bool) data_get($runtime, 'canReadNativeHistory', $defaults['history_read']),
            'tabs_read' => (bool) data_get($runtime, 'canReadAllTabs', data_get($runtime, 'canReadCurrentTab', $defaults['tabs_read'])),
            'tab_commands' => (bool) data_get($runtime, 'canOpenTab', $defaults['tab_commands']),
            'native_bookmark_write' => (bool) data_get($runtime, 'canWriteNativeBookmarks', $defaults['native_bookmark_write']),
            'reliable_background_sync' => (bool) data_get($runtime, 'canUseBackgroundPolling', $defaults['reliable_background_sync']),
            'history_mode' => data_get($runtime, 'historyMode', data_get($runtime, 'history_mode', 'native')),
            'capability_probes' => data_get($runtime, 'probes', []),
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'capabilities_json' => 'array',
            'last_seen_at' => 'datetime',
        ];
    }
}
