<?php

namespace App\Models;

use Database\Factories\BookmarkBackupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id',
    'sync_run_id',
    'payload_json',
    'encrypted_payload',
    'created_at',
])]
class BookmarkBackup extends Model
{
    /** @use HasFactory<BookmarkBackupFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    public function syncRun(): BelongsTo
    {
        return $this->belongsTo(BookmarkSyncRun::class, 'sync_run_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'created_at' => 'datetime',
        ];
    }
}
