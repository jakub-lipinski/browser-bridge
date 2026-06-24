<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NormalizedBookmarkResource extends JsonResource
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
            'external_id' => $this->external_id,
            'parent_external_id' => $this->parent_external_id,
            'type' => $this->type,
            'title' => $this->title,
            'url' => $this->url,
            'path' => $this->path_json ?? [],
            'date_added' => $this->date_added?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
