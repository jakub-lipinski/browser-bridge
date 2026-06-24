<?php

namespace App\Models;

use App\Enums\BookmarkSyncDirection;
use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncTargetScope;
use Database\Factories\BookmarkSyncProfileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'name',
    'source_device_id',
    'target_device_id',
    'mode',
    'direction',
    'target_scope',
    'selected_target_folder_id',
    'auto_sync_enabled',
    'auto_sync_interval_minutes',
    'last_run_at',
    'next_run_at',
    'is_active',
])]
class BookmarkSyncProfile extends Model
{
    /** @use HasFactory<BookmarkSyncProfileFactory> */
    use HasFactory;

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(BookmarkSyncRun::class, 'profile_id');
    }

    public function latestRun(): HasOne
    {
        return $this->hasOne(BookmarkSyncRun::class, 'profile_id')->latestOfMany();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => BookmarkSyncMode::class,
            'direction' => BookmarkSyncDirection::class,
            'target_scope' => BookmarkSyncTargetScope::class,
            'auto_sync_enabled' => 'boolean',
            'auto_sync_interval_minutes' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }
}
