<?php

namespace App\Http\Requests\Concerns;

use App\Services\UrlSanitizer;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use JsonException;

trait ValidatesBrowserBridgePayloads
{
    public function rejectOversizedJson(Validator $validator, string $key, int $maxBytes): void
    {
        try {
            $encodedPayload = json_encode($this->input($key), JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $validator->errors()->add($key, 'The payload must be valid JSON.');

            return;
        }

        if (strlen($encodedPayload) > $maxBytes) {
            $validator->errors()->add($key, "The payload is too large. Maximum size is {$maxBytes} bytes.");
        }
    }

    public function rejectInvalidProvidedUrls(Validator $validator, string $itemsKey): void
    {
        $sanitizer = app(UrlSanitizer::class);
        $items = $this->input($itemsKey, []);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $url = Arr::get($item, 'url');

            if ($url === null || $url === '' || $sanitizer->isBlockedInternalUrl($url)) {
                continue;
            }

            if (! $sanitizer->isValidWebUrl($url)) {
                $validator->errors()->add("{$itemsKey}.{$index}.url", 'The URL must be a valid http or https URL.');
            }
        }
    }

    public function rejectUnsyncableProvidedUrls(Validator $validator, string $itemsKey): void
    {
        $sanitizer = app(UrlSanitizer::class);
        $items = $this->input($itemsKey, []);

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            if (! $sanitizer->isSyncableUrl(Arr::get($item, 'url'))) {
                $validator->errors()->add("{$itemsKey}.{$index}.url", 'The URL must be a syncable http or https URL.');
            }
        }
    }

    public function rejectUnsyncableUrl(Validator $validator, string $key): void
    {
        $url = $this->input($key);
        $sanitizer = app(UrlSanitizer::class);

        if (! is_string($url) || ! $sanitizer->isSyncableUrl($url)) {
            $validator->errors()->add($key, 'The URL must be a syncable http or https URL.');
        }
    }
}
