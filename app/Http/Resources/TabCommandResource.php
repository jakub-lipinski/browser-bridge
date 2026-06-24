<?php

namespace App\Http\Resources;

use App\Enums\TabCommandStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TabCommandResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'source_device_id' => $this->source_device_id,
            'target_device_id' => $this->target_device_id,
            'source_device' => new DeviceResource($this->whenLoaded('sourceDevice')),
            'target_device' => new DeviceResource($this->whenLoaded('targetDevice')),
            'url' => $this->url,
            'title' => $this->title,
            'status' => $this->status instanceof TabCommandStatus ? $this->status->value : $this->status,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
