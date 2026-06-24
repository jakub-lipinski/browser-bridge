<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TabSnapshotResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'device_id' => $this->device_id,
            'tab_count' => $this->tab_count,
            'payload_json' => $this->payload_json,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
