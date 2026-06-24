<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

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
            'device_uuid' => ['required', 'uuid'],
            'name' => ['required', 'string', 'max:120'],
            'browser' => ['required', 'string', 'max:64'],
            'platform' => ['required', 'string', 'max:64'],
        ];
    }
}
