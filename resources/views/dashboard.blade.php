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
                        Local admin view for registered browsers, latest sync snapshots, history counts, and pending send-tab commands.
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

            <section class="flex flex-col gap-4 rounded-lg border border-amber-200 bg-amber-50 p-5 text-sm text-amber-950 md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="text-base font-semibold">BrowserBridge History retention</h2>
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

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Devices</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['devices'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Bookmark snapshots</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['bookmarkSnapshots'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Tab snapshots</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['tabSnapshots'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">History items</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['historyItems'] }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Tab commands</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $storageCounts['tabCommands'] }}</p>
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
                                    <th class="px-5 py-3 text-right">Latest bookmarks</th>
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
                                        <td class="px-5 py-4 text-zinc-700">
                                            {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}
                                        </td>
                                        <td class="px-5 py-4 text-right text-zinc-700">
                                            {{ $device->latestTabSnapshot?->tab_count ?? 0 }}
                                        </td>
                                        <td class="px-5 py-4 text-right text-zinc-700">
                                            {{ $device->latestBookmarkSnapshot?->item_count ?? 0 }}
                                        </td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->history_items_count }}</td>
                                        <td class="px-5 py-4 text-right text-zinc-700">{{ $device->pending_tab_commands_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
                <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    <div class="border-b border-zinc-200 px-5 py-4">
                        <h2 class="text-base font-semibold">Latest history items</h2>
                    </div>

                    @if ($latestHistoryItems->isEmpty())
                        <div class="px-5 py-8 text-sm text-zinc-500">
                            No history items stored.
                        </div>
                    @else
                        <div class="divide-y divide-zinc-100">
                            @foreach ($latestHistoryItems as $historyItem)
                                <div class="px-5 py-4">
                                    <div class="truncate text-sm font-medium text-zinc-950">{{ $historyItem->title ?: $historyItem->url }}</div>
                                    <div class="mt-1 truncate text-xs text-zinc-500">{{ $historyItem->url }}</div>
                                    <div class="mt-2 text-xs text-zinc-500">
                                        {{ $historyItem->device?->name ?? 'Unknown device' }} - {{ $historyItem->visited_at?->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>

                <section class="overflow-hidden rounded-lg border border-zinc-200 bg-white">
                    <div class="border-b border-zinc-200 px-5 py-4">
                        <h2 class="text-base font-semibold">Pending tab commands</h2>
                    </div>

                    @if ($pendingTabCommands->isEmpty())
                        <div class="px-5 py-8 text-sm text-zinc-500">
                            No pending tab commands.
                        </div>
                    @else
                        <div class="divide-y divide-zinc-100">
                            @foreach ($pendingTabCommands as $tabCommand)
                                <div class="px-5 py-4">
                                    <div class="truncate text-sm font-medium text-zinc-950">{{ $tabCommand->title ?: $tabCommand->url }}</div>
                                    <div class="mt-1 truncate text-xs text-zinc-500">{{ $tabCommand->url }}</div>
                                    <div class="mt-2 text-xs text-zinc-500">
                                        {{ $tabCommand->sourceDevice?->name ?? 'Unknown source' }}
                                        to
                                        {{ $tabCommand->targetDevice?->name ?? 'Unknown target' }}
                                        - {{ $tabCommand->created_at?->diffForHumans() }}
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            </div>
        </main>
    </body>
</html>
