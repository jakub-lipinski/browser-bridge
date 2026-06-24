<?php

namespace App\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UrlSanitizer
{
    /**
     * @var array<int, string>
     */
    private const BLOCKED_SCHEMES = [
        'about',
        'brave',
        'chrome',
        'devtools',
        'edge',
        'file',
        'javascript',
        'safari-extension',
        'view-source',
    ];

    /**
     * @var array<int, string>
     */
    private const ALLOWED_SCHEMES = ['http', 'https'];

    public function isBlockedInternalUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $normalizedUrl = Str::lower($url);
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (is_string($scheme) && in_array(Str::lower($scheme), self::BLOCKED_SCHEMES, true)) {
            return true;
        }

        return Str::startsWith($normalizedUrl, array_map(
            fn (string $scheme): string => $scheme.':',
            self::BLOCKED_SCHEMES,
        ));
    }

    public function isValidWebUrl(?string $url): bool
    {
        if (! is_string($url) || $url === '') {
            return false;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        return filter_var($url, FILTER_VALIDATE_URL) !== false
            && is_string($scheme)
            && in_array(Str::lower($scheme), self::ALLOWED_SCHEMES, true);
    }

    public function isSyncableUrl(?string $url): bool
    {
        return $this->isValidWebUrl($url) && ! $this->isBlockedInternalUrl($url);
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
