<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesBrowserBridgePayloads;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class HistoryBatchRequest extends FormRequest
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
        $maxBatchSize = (int) config('browserbridge.max_history_batch_size');

        return [
            'device_uuid' => ['required', 'uuid'],
            'history_sync_enabled' => ['required', 'accepted'],
            'items' => ['required', 'array', 'max:'.$maxBatchSize],
            'items.*.url' => ['required', 'string', 'max:2048'],
            'items.*.title' => ['nullable', 'string', 'max:512'],
            'items.*.visited_at' => ['required', 'date'],
        ];
    }

    /**
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $this->rejectUnsyncableProvidedUrls($validator, 'items');
            },
        ];
    }
}
