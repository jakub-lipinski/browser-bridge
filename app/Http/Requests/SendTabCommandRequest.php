<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBrowserBridgePayloads;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SendTabCommandRequest extends FormRequest
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
            'source_device_uuid' => ['required', 'uuid', 'different:target_device_uuid'],
            'target_device_uuid' => ['required', 'uuid'],
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['nullable', 'string', 'max:512'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnsyncableUrl($validator, 'url');
            },
        ];
    }
}
