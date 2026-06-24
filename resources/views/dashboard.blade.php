<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BrowserBridge Dashboard</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="bg-zinc-50 font-sans text-zinc-950 antialiased">
        <main class="mx-auto flex min-h-screen w-full max-w-7xl flex-col gap-8 px-4 py-8 sm:px-6 lg:px-8">
            <header class="flex flex-col gap-4 border-b border-zinc-200 pb-6 lg:flex-row lg:items-end lg:justify-between">
                <div>
                    <p class="text-sm font-medium text-teal-700">BrowserBridge local cloud</p>
                    <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Sync dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600">
                        Local admin view for devices, tab handoff, recent open tabs, and searchable BrowserBridge data.
                    </p>
                </div>
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    Local/private build. Do not expose publicly until authentication, encryption, privacy policy and rate limiting are complete.
                </div>
            </header>

            @if (session('status'))
                <div class="rounded-lg border border-teal-200 bg-teal-50 px-4 py-3 text-sm text-teal-950">
                    {{ session('status') }}
                </div>
            @endif

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Devices</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['devices'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Bookmarks</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['normalizedBookmarks'] }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $storageCounts['bookmarkSnapshots'] }} snapshots</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Latest open tabs</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $latestTabSnapshots->sum('tab_count') }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $storageCounts['tabSnapshots'] }} snapshots</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">History items</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['historyItems'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Pending commands</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $devices->sum('pending_tab_commands_count') }}</p>
                    <p class="mt-1 text-xs text-zinc-500">{{ $storageCounts['tabCommands'] }} total commands</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <h2 class="text-base font-semibold">Registered devices</h2>
                </div>

                @if ($devices->isEmpty())
                    <div class="px-5 py-10 text-sm text-zinc-500">
                        No devices have registered yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase text-zinc-500">
                                <tr>
                                    <th class="px-5 py-3">Device</th>
                                    <th class="px-5 py-3">Browser</th>
                                    <th class="px-5 py-3">Platform</th>
                                    <th class="px-5 py-3">Last seen</th>
                                    <th class="px-5 py-3 text-right">Latest tabs</th>
                                    <th class="px-5 py-3 text-right">Bookmarks</th>
                                    <th class="px-5 py-3 text-right">History</th>
                                    <th class="px-5 py-3 text-right">Pending commands</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($devices as $device)
                                    <tr>
                                        <td class="px-5 py-4">
                                            <div class="font-medium text-zinc-950">{{ $device->name }}</div>
                                            <div class="mt-1 font-mono text-xs text-zinc-500">{{ $device->uuid }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $device->browser }}</td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $device->platform }}</td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->latestTabSnapshot?->tab_count ?? 0 }}</td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->normalized_bookmarks_count }}</td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->history_items_count }}</td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->pending_tab_commands_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                        <div>
                            <h2 class="text-base font-semibold">Pending and recent tab commands</h2>
                            <p class="mt-1 text-sm text-zinc-500">Tab handoff is the main BrowserBridge workflow, so command status stays visible here.</p>
                        </div>
                        <span class="rounded-full bg-teal-50 px-3 py-1 text-xs font-semibold text-teal-800">
                            {{ $devices->sum('pending_tab_commands_count') }} pending
                        </span>
                    </div>
                </div>

                @if ($tabCommands->isEmpty())
                    <div class="px-5 py-8 text-sm text-zinc-500">
                        No tab commands yet.
                    </div>
                @else
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-zinc-200 text-left text-sm">
                            <thead class="bg-zinc-50 text-xs font-semibold uppercase text-zinc-500">
                                <tr>
                                    <th class="px-5 py-3">Tab</th>
                                    <th class="px-5 py-3">Source</th>
                                    <th class="px-5 py-3">Target</th>
                                    <th class="px-5 py-3">Status</th>
                                    <th class="px-5 py-3">Created</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-zinc-100">
                                @foreach ($tabCommands as $tabCommand)
                                    <tr>
                                        <td class="max-w-md px-5 py-4">
                                            <a href="{{ $tabCommand->url }}" target="_blank" rel="noreferrer" class="block truncate font-medium text-zinc-950 hover:text-teal-700">
                                                {{ $tabCommand->title ?: $tabCommand->url }}
                                            </a>
                                            <div class="mt-1 truncate text-xs text-zinc-500">{{ $tabCommand->url }}</div>
                                        </td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $tabCommand->sourceDevice?->name ?? 'Unknown source' }}</td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $tabCommand->targetDevice?->name ?? 'Unknown target' }}</td>
                                        <td class="px-5 py-4">
                                            <span class="rounded-full px-2 py-1 text-xs font-semibold {{ $tabCommand->status === \App\Enums\TabCommandStatus::Pending ? 'bg-amber-100 text-amber-900' : 'bg-zinc-100 text-zinc-700' }}">
                                                {{ $tabCommand->status->value }}
                                            </span>
                                        </td>
                                        <td class="px-5 py-4 text-zinc-700">{{ $tabCommand->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                <div class="border-b border-zinc-200 px-5 py-4">
                    <h2 class="text-base font-semibold">Latest open tabs</h2>
                    <p class="mt-1 text-sm text-zinc-500">Most recent tab snapshot per device, capped to five visible tabs each.</p>
                </div>

                @if ($latestTabSnapshots->isEmpty())
                    <div class="px-5 py-8 text-sm text-zinc-500">
                        No open tab snapshots stored.
                    </div>
                @else
                    <div class="grid gap-4 p-5 lg:grid-cols-2">
                        @foreach ($latestTabSnapshots as $tabSnapshot)
                            @php
                                $tabs = collect($tabSnapshot->payload_json['tabs'] ?? []);
                                $visibleTabs = $tabs->take(5);
                                $hiddenTabs = $tabs->skip(5);
                            @endphp
                            <article class="rounded-lg border border-zinc-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <h3 class="text-sm font-semibold text-zinc-950">{{ $tabSnapshot->device?->name ?? 'Unknown device' }}</h3>
                                        <p class="mt-1 text-xs text-zinc-500">{{ $tabSnapshot->tab_count }} tabs - {{ $tabSnapshot->created_at?->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="mt-4 grid gap-2">
                                    @foreach ($visibleTabs as $tab)
                                        <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="block rounded-md border border-zinc-100 px-3 py-2 hover:border-teal-600">
                                            <div class="truncate text-sm font-medium text-zinc-950">{{ $tab['title'] ?? $tab['url'] ?? 'Untitled tab' }}</div>
                                            <div class="mt-1 truncate text-xs text-zinc-500">{{ $tab['url'] ?? '' }}</div>
                                        </a>
                                    @endforeach
                                </div>
                                @if ($hiddenTabs->isNotEmpty())
                                    <details class="mt-3">
                                        <summary class="cursor-pointer text-sm font-semibold text-teal-700">Show {{ $hiddenTabs->count() }} more</summary>
                                        <div class="mt-3 grid gap-2">
                                            @foreach ($hiddenTabs as $tab)
                                                <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="block rounded-md border border-zinc-100 px-3 py-2 hover:border-teal-600">
                                                    <div class="truncate text-sm font-medium text-zinc-950">{{ $tab['title'] ?? $tab['url'] ?? 'Untitled tab' }}</div>
                                                    <div class="mt-1 truncate text-xs text-zinc-500">{{ $tab['url'] ?? '' }}</div>
                                                </a>
                                            @endforeach
                                        </div>
                                    </details>
                                @endif
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white"
                    data-dashboard-browser
                    data-kind="bookmarks"
                    data-endpoint="{{ route('dashboard.bookmarks') }}"
                    data-limit="12"
                >
                    <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-base font-semibold">BrowserBridge Bookmarks</h2>
                                <p class="mt-1 text-sm text-zinc-500">Latest 12 by default. Search updates while typing.</p>
                            </div>
                            <span class="text-sm text-zinc-500" data-result-count>{{ $bookmarkTotal }} total</span>
                        </div>
                        <input data-search-input type="search" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" placeholder="Search bookmarks">
                    </div>

                    <div data-loading class="hidden px-5 py-4 text-sm text-zinc-500">Loading bookmarks...</div>
                    <div data-error class="hidden px-5 py-4 text-sm text-red-700">Could not load bookmarks.</div>
                    <div data-empty class="{{ $browserBridgeBookmarks->isEmpty() ? '' : 'hidden' }} px-5 py-8 text-sm text-zinc-500">No BrowserBridge bookmarks found.</div>
                    <div data-results class="divide-y divide-zinc-100">
                        @foreach ($browserBridgeBookmarks as $deviceName => $bookmarks)
                            <div class="px-5 py-4" data-result-group>
                                <h3 class="text-sm font-semibold text-zinc-950">{{ $deviceName }}</h3>
                                <div class="mt-3 grid gap-2">
                                    @foreach ($bookmarks as $bookmark)
                                        <a href="{{ $bookmark->url }}" class="rounded-md border border-zinc-100 px-3 py-2 hover:border-teal-600" target="_blank" rel="noreferrer">
                                            <div class="truncate text-sm font-medium text-zinc-950">{{ $bookmark->title ?: $bookmark->url }}</div>
                                            <div class="mt-1 truncate text-xs text-zinc-500">{{ $bookmark->url }}</div>
                                            @if (! empty($bookmark->path_json))
                                                <div class="mt-1 truncate text-xs text-zinc-400">{{ implode(' / ', $bookmark->path_json) }}</div>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="border-t border-zinc-100 px-5 py-4">
                        <button data-load-more type="button" class="{{ $bookmarkTotal > 12 ? '' : 'hidden' }} rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Show more</button>
                    </div>
                </section>

                <section
                    class="overflow-hidden rounded-lg border border-zinc-200 bg-white"
                    data-dashboard-browser
                    data-kind="history"
                    data-endpoint="{{ route('dashboard.history') }}"
                    data-limit="10"
                >
                    <div class="flex flex-col gap-3 border-b border-zinc-200 px-5 py-4">
                        <div class="flex flex-col gap-2 md:flex-row md:items-center md:justify-between">
                            <div>
                                <h2 class="text-base font-semibold">BrowserBridge History</h2>
                                <p class="mt-1 text-sm text-zinc-500">Recent 10 by default. Search updates while typing.</p>
                            </div>
                            <span class="text-sm text-zinc-500" data-result-count>{{ $historyTotal }} total</span>
                        </div>
                        <input data-search-input type="search" class="w-full rounded-md border border-zinc-300 px-3 py-2 text-sm" placeholder="Search history">
                    </div>

                    <div data-loading class="hidden px-5 py-4 text-sm text-zinc-500">Loading history...</div>
                    <div data-error class="hidden px-5 py-4 text-sm text-red-700">Could not load history.</div>
                    <div data-empty class="{{ $latestHistoryItems->isEmpty() ? '' : 'hidden' }} px-5 py-8 text-sm text-zinc-500">No BrowserBridge history found.</div>
                    <div data-results class="divide-y divide-zinc-100">
                        @foreach ($latestHistoryItems as $historyItem)
                            <a href="{{ $historyItem->url }}" target="_blank" rel="noreferrer" class="block px-5 py-4 hover:bg-zinc-50">
                                <div class="truncate text-sm font-medium text-zinc-950">{{ $historyItem->title ?: $historyItem->url }}</div>
                                <div class="mt-1 truncate text-xs text-zinc-500">{{ $historyItem->url }}</div>
                                <div class="mt-2 text-xs text-zinc-500">
                                    {{ $historyItem->device?->name ?? 'Unknown device' }} - {{ $historyItem->visited_at?->diffForHumans() }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div class="border-t border-zinc-100 px-5 py-4">
                        <button data-load-more type="button" class="{{ $historyTotal > 10 ? '' : 'hidden' }} rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white hover:bg-zinc-700">Show more</button>
                    </div>
                </section>
            </div>

            <section class="flex flex-col gap-4 rounded-lg border border-red-200 bg-red-50 p-5 text-sm text-red-950 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">Danger zone</h2>
                    <p class="mt-1">
                        Synced history is retained for {{ config('browserbridge.history_retention_days') }} days by default and is only a BrowserBridge shared view.
                    </p>
                </div>
                <form method="POST" action="{{ route('dashboard.history.destroy') }}" onsubmit="return confirm('Delete all synced BrowserBridge history?');">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="rounded-md bg-red-700 px-3 py-2 text-sm font-semibold text-white hover:bg-red-800">
                        Delete synced history
                    </button>
                </form>
            </section>
        </main>
    </body>
</html>
