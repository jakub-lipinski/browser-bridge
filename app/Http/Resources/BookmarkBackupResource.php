<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkBackupResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'device' => new DeviceResource($this->whenLoaded('device')),
            'sync_run_id' => $this->sync_run_id,
            'has_payload' => filled($this->payload_json),
            'has_encrypted_payload' => filled($this->encrypted_payload),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
