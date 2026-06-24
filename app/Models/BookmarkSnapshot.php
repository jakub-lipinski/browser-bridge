<?php

namespace App\Models;

use Database\Factories\BookmarkSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'payload_json', 'encrypted_payload', 'item_count'])]
class BookmarkSnapshot extends Model
{
    /** @use HasFactory<BookmarkSnapshotFactory> */
    use HasFactory;

    public function device(): BelongsTo
    {
        return $this->belongsTo(Device::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload_json' => 'array',
            'item_count' => 'integer',
        ];
    }
}
