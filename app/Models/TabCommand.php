<?php

namespace App\Models;

use App\Enums\TabCommandStatus;
use Database\Factories\TabCommandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source_device_id', 'target_device_id', 'url', 'title', 'encrypted_payload', 'status'])]
class TabCommand extends Model
{
    /** @use HasFactory<TabCommandFactory> */
    use HasFactory;

    public function sourceDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'source_device_id');
    }

    public function targetDevice(): BelongsTo
    {
        return $this->belongsTo(Device::class, 'target_device_id');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TabCommandStatus::class,
        ];
    }
}
