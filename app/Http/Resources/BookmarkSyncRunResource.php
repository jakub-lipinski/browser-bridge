<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkSyncRunResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'profile_id' => $this->profile_id,
            'profile' => new BookmarkSyncProfileResource($this->whenLoaded('profile')),
            'source_device_id' => $this->source_device_id,
            'source_device' => new DeviceResource($this->whenLoaded('sourceDevice')),
            'target_device_id' => $this->target_device_id,
            'target_device' => new DeviceResource($this->whenLoaded('targetDevice')),
            'mode' => $this->mode?->value,
            'status' => $this->status?->value,
            'added_count' => $this->added_count,
            'updated_count' => $this->updated_count,
            'moved_count' => $this->moved_count,
            'deleted_count' => $this->deleted_count,
            'skipped_count' => $this->skipped_count,
            'duplicate_count' => $this->duplicate_count,
            'invalid_count' => $this->invalid_count,
            'error_message' => $this->error_message,
            'preview' => $this->preview_json,
            'result' => $this->result_json,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
