<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBrowserBridgePayloads;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
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
            'source_device_uuid' => ['required', 'uuid', Rule::exists('devices', 'uuid')],
            'target_device_uuid' => ['required', 'uuid', Rule::exists('devices', 'uuid')],
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
                if (
                    ! (bool) config('browserbridge.allow_same_device_tab_commands')
                    && $this->input('source_device_uuid') === $this->input('target_device_uuid')
                ) {
                    $validator->errors()->add(
                        'target_device_uuid',
                        'The target device must be different from the source device.',
                    );
                }

                $this->rejectUnsyncableUrl($validator, 'url');
            },
        ];
    }
}
