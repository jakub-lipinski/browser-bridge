<?php

namespace App\Models;

use Database\Factories\TabSnapshotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['device_id', 'payload_json', 'encrypted_payload', 'tab_count'])]
class TabSnapshot extends Model
{
    /** @use HasFactory<TabSnapshotFactory> */
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
            'tab_count' => 'integer',
        ];
    }
}
