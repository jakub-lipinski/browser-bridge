<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBrowserBridgePayloads;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class TabSnapshotRequest extends FormRequest
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
            'tabs' => ['required', 'array', 'max:500'],
            'tabs.*.url' => ['required', 'string', 'max:2048'],
            'tabs.*.title' => ['nullable', 'string', 'max:512'],
            'tabs.*.active' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectOversizedJson($validator, 'tabs', (int) config('browserbridge.max_tab_snapshot_payload_bytes'));
                $this->rejectInvalidProvidedUrls($validator, 'tabs');
            },
        ];
    }
}
