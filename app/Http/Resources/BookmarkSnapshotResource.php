<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkSnapshotResource extends JsonResource
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
            'item_count' => $this->item_count,
            'payload_json' => $this->payload_json,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
