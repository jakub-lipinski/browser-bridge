<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class BookmarkBackupRequest extends FormRequest
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
            'sync_run_id' => ['nullable', 'integer', 'exists:bookmark_sync_runs,id'],
            'payload' => ['nullable', 'array'],
            'encrypted_payload' => ['nullable', 'string'],
        ];
    }
}
