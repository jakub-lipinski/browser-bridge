<?php

namespace App\Models;

use Database\Factories\NormalizedBookmarkFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'device_id',
    'external_id',
    'parent_external_id',
    'type',
    'title',
    'url',
    'path_json',
    'date_added',
])]
class NormalizedBookmark extends Model
{
    /** @use HasFactory<NormalizedBookmarkFactory> */
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
            'path_json' => 'array',
            'date_added' => 'datetime',
        ];
    }
}
