<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookmarkSyncRunRequest extends FormRequest
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
            'confirm_mirror' => ['sometimes', 'boolean'],
            'backup_created' => ['sometimes', 'boolean'],
            'backup_payload' => ['nullable', 'array'],
            'operation_log' => ['nullable', 'array'],
            'result' => ['nullable', 'array'],
        ];
    }
}
