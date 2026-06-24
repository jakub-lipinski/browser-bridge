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
        return [
            'device_uuid' => ['required', 'uuid'],
            'items' => ['required', 'array', 'max:5000'],
            'items.*.url' => ['nullable', 'string', 'max:2048'],
            'items.*.title' => ['nullable', 'string', 'max:512'],
            'items.*.folder' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectOversizedJson($validator, 'items', (int) config('browserbridge.max_bookmark_snapshot_payload_bytes'));
                $this->rejectInvalidProvidedUrls($validator, 'items');
            },
        ];
    }
}
