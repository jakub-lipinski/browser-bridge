<?php

namespace App\Http\Requests;

use App\Enums\BookmarkSyncDirection;
use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncTargetScope;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BookmarkSyncProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'device_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'source_device_id' => ['required', 'integer', 'exists:devices,id'],
            'target_device_id' => ['required', 'integer', 'exists:devices,id', 'different:source_device_id'],
            'mode' => ['required', Rule::enum(BookmarkSyncMode::class)],
            'direction' => ['required', Rule::enum(BookmarkSyncDirection::class)],
            'target_scope' => ['required', Rule::enum(BookmarkSyncTargetScope::class)],
            'selected_target_folder_id' => ['nullable', 'string', 'max:255'],
            'auto_sync_enabled' => ['sometimes', 'boolean'],
            'auto_sync_interval_minutes' => ['nullable', 'integer', Rule::in([5, 15, 30, 60, 1440])],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
