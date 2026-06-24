<?php

namespace App\Models;

use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncRunStatus;
use Database\Factories\BookmarkSyncRunFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'profile_id',
    'source_device_id',
    'target_device_id',
    'mode',
    'status',
    'added_count',
    'updated_count',
    'moved_count',
    'deleted_count',
    'skipped_count',
    'duplicate_count',
    'invalid_count',
    'error_message',
    'preview_json',
    'result_json',
])]
class BookmarkSyncRun extends Model
{
    /** @use HasFactory<BookmarkSyncRunFactory> */
    use HasFactory;

    public function profile(): BelongsTo
    {
        return $this->belongsTo(BookmarkSyncProfile::class, 'profile_id');
    }

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    public function backups(): HasMany
    {
        return $this->hasMany(BookmarkBackup::class, 'sync_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'mode' => BookmarkSyncMode::class,
            'status' => BookmarkSyncRunStatus::class,
            'added_count' => 'integer',
            'updated_count' => 'integer',
            'moved_count' => 'integer',
            'deleted_count' => 'integer',
            'skipped_count' => 'integer',
            'duplicate_count' => 'integer',
            'invalid_count' => 'integer',
            'preview_json' => 'array',
            'result_json' => 'array',
        ];
    }
}
