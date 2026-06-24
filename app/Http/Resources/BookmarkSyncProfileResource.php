<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BookmarkSyncProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'source_device_id' => $this->source_device_id,
            'source_device' => new DeviceResource($this->whenLoaded('sourceDevice')),
            'target_device_id' => $this->target_device_id,
            'target_device' => new DeviceResource($this->whenLoaded('targetDevice')),
            'mode' => $this->mode?->value,
            'direction' => $this->direction?->value,
            'target_scope' => $this->target_scope?->value,
            'selected_target_folder_id' => $this->selected_target_folder_id,
            'auto_sync_enabled' => $this->auto_sync_enabled,
            'auto_sync_interval_minutes' => $this->auto_sync_interval_minutes,
            'last_run_at' => $this->last_run_at?->toIso8601String(),
            'next_run_at' => $this->next_run_at?->toIso8601String(),
            'is_active' => $this->is_active,
            'latest_run' => new BookmarkSyncRunResource($this->whenLoaded('latestRun')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
