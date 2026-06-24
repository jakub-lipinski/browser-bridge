<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBrowserBridgePayloads;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class BookmarkSnapshotRequest extends FormRequest
{
    use ValidatesBrowserBridgePayloads;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $maxItems = (int) config('browserbridge.max_bookmark_items_per_device');

        return [
            'device_uuid' => ['required', 'uuid'],
            'items' => ['required', 'array', 'max:'.$maxItems],
            'items.*.external_id' => ['nullable', 'string', 'max:255'],
            'items.*.parent_external_id' => ['nullable', 'string', 'max:255'],
            'items.*.id' => ['nullable', 'string', 'max:255'],
            'items.*.parentId' => ['nullable', 'string', 'max:255'],
            'items.*.type' => ['nullable', 'string', 'in:folder,bookmark'],
            'items.*.url' => ['nullable', 'string', 'max:2048'],
            'items.*.title' => ['nullable', 'string', 'max:512'],
            'items.*.folder' => ['nullable', 'string', 'max:512'],
            'items.*.path' => ['nullable', 'array', 'max:50'],
            'items.*.path.*' => ['nullable', 'string', 'max:512'],
            'items.*.date_added' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectOversizedJson($validator, 'items', (int) config('browserbridge.max_bookmark_snapshot_size'));
                $this->rejectInvalidProvidedUrls($validator, 'items');
            },
        ];
    }
}
