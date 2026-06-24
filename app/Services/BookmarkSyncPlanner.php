<?php

namespace App\Services;

use App\Enums\BookmarkSyncMode;
use App\Enums\BookmarkSyncTargetScope;
use App\Models\BookmarkSyncProfile;
use App\Models\NormalizedBookmark;
use Illuminate\Support\Collection;

class BookmarkSyncPlanner
{
    /**
     * @return array<string, mixed>
     */
    public function preview(BookmarkSyncProfile $profile): array
    {
        $profile->loadMissing(['sourceDevice', 'targetDevice']);
        $sourceBookmarks = $this->sourceBookmarks($profile);
        $validBookmarks = $sourceBookmarks->filter(fn (NormalizedBookmark $bookmark): bool => filled($bookmark->url));
        $duplicateCount = $this->duplicateCount($validBookmarks);
        $invalidCount = $sourceBookmarks->count() - $validBookmarks->count();
        $uniqueBookmarks = $validBookmarks
            ->unique(fn (NormalizedBookmark $bookmark): string => $this->normalizedUrl((string) $bookmark->url))
            ->values();

        $warnings = $this->warnings($profile);
        $addCount = $profile->mode === BookmarkSyncMode::Mirror ? $uniqueBookmarks->count() : $uniqueBookmarks->count();

        return [
            'mode' => $profile->mode->value,
            'source_device' => $profile->sourceDevice?->name,
            'target_device' => $profile->targetDevice?->name,
            'add_count' => $addCount,
            'update_count' => 0,
            'move_count' => 0,
            'delete_count' => 0,
            'skip_count' => $duplicateCount,
            'duplicate_count' => $duplicateCount,
            'invalid_count' => $invalidCount,
            'warnings' => $warnings,
            'sample_changes' => $uniqueBookmarks
                ->take(10)
                ->map(fn (NormalizedBookmark $bookmark): array => [
                    'action' => 'add',
                    'title' => $bookmark->title,
                    'url' => $bookmark->url,
                    'path' => $bookmark->path_json ?? [],
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function countsFromPreview(array $preview): array
    {
        return [
            'added_count' => (int) ($preview['add_count'] ?? 0),
            'updated_count' => (int) ($preview['update_count'] ?? 0),
            'moved_count' => (int) ($preview['move_count'] ?? 0),
            'deleted_count' => (int) ($preview['delete_count'] ?? 0),
            'skipped_count' => (int) ($preview['skip_count'] ?? 0),
            'duplicate_count' => (int) ($preview['duplicate_count'] ?? 0),
            'invalid_count' => (int) ($preview['invalid_count'] ?? 0),
        ];
    }

    /**
     * @return Collection<int, NormalizedBookmark>
     */
    private function sourceBookmarks(BookmarkSyncProfile $profile): Collection
    {
        return NormalizedBookmark::query()
            ->where('device_id', $profile->source_device_id)
            ->where('type', 'bookmark')
            ->orderBy('title')
            ->get();
    }

    /**
     * @param  Collection<int, NormalizedBookmark>  $bookmarks
     */
    private function duplicateCount(Collection $bookmarks): int
    {
        return $bookmarks
            ->map(fn (NormalizedBookmark $bookmark): string => $this->normalizedUrl((string) $bookmark->url))
            ->duplicates()
            ->count();
    }

    /**
     * @return array<int, string>
     */
    private function warnings(BookmarkSyncProfile $profile): array
    {
        $warnings = [];

        if ($profile->mode === BookmarkSyncMode::SafeFolder) {
            $warnings[] = 'Safe Folder Import only writes inside a BrowserBridge-managed folder and never deletes native bookmarks.';
        }

        if ($profile->mode === BookmarkSyncMode::Merge) {
            $warnings[] = 'Merge adds missing bookmarks and does not delete anything.';
        }

        if ($profile->mode === BookmarkSyncMode::Mirror) {
            $warnings[] = 'Mirror may delete or move bookmarks in the selected destination scope. BrowserBridge requires confirmation and a backup first.';
        }

        if (
            $profile->mode === BookmarkSyncMode::Mirror
            && $profile->target_scope === BookmarkSyncTargetScope::EntireBookmarksRoot
        ) {
            $warnings[] = 'Full-root Mirror is disabled in this build. Use a BrowserBridge-managed folder or selected folder scope.';
        }

        if ($profile->targetDevice?->browser === 'safari') {
            $warnings[] = 'Native Safari bookmark writing is not available in this Safari version. Safari can still display BrowserBridge bookmarks and send tabs.';
        }

        return $warnings;
    }

    private function normalizedUrl(string $url): string
    {
        return rtrim(mb_strtolower(trim($url)), '/');
    }
}
