<?php

namespace App\Models;

use Database\Factories\DeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['uuid', 'name', 'browser', 'platform', 'last_seen_at'])]
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
     * @return array<string, bool>
     */
    public function capabilities(): array
    {
        return match ($this->browser) {
            'chrome' => [
                'bookmarks_read' => true,
                'history_read' => true,
                'tabs_read' => true,
                'tab_commands' => true,
                'native_bookmark_write' => false,
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
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
        ];
    }
}
