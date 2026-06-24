<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HistorySearchRequest extends FormRequest
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
        $maxLimit = (int) config('browserbridge.history_search_limit');

        return [
            'device_uuid' => ['required', 'uuid'],
            'query' => ['nullable', 'string', 'max:200'],
            'q' => ['nullable', 'string', 'max:200'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.$maxLimit],
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('q') && ! $this->has('query')) {
            $this->merge(['query' => $this->query('q')]);
        }
    }
}
