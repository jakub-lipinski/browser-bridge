<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class BrowserDataSanitizer
{
    /**
     * @var array<int, string>
     */
    private const INTERNAL_SCHEMES = ['about', 'chrome', 'edge', 'file'];

    public function isInternalUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (is_string($scheme) && in_array(Str::lower($scheme), self::INTERNAL_SCHEMES, true)) {
            return true;
        }

        return Str::startsWith(Str::lower($url), ['about:']);
    }

    public function isValidWebUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(Str::lower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true);
    }

    public function isSyncableUrl(?string $url): bool
    {
        return $this->isValidWebUrl($url) && ! $this->isInternalUrl($url);
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    public function filterSyncableItems(array $items): array
    {
        return collect($items)
            ->filter(fn (array $item): bool => $this->isSyncableUrl(Arr::get($item, 'url')))
            ->values()
            ->all();
    }
}
