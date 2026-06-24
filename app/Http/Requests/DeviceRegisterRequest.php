<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DeviceRegisterRequest extends FormRequest
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
            'device_uuid' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'browser' => ['required', 'string', Rule::in(['chrome', 'safari'])],
            'platform' => ['required', 'string', Rule::in(['windows', 'macos', 'ios'])],
            'capabilities' => ['nullable', 'array'],
        ];
    }
}
