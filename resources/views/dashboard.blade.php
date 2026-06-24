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
            <header class="flex flex-col gap-4 border-b border-zinc-200 pb-6 sm:flex-row sm:items-end sm:justify-between">
                <div>
                    <p class="text-sm font-medium text-teal-700">BrowserBridge local cloud</p>
                    <h1 class="mt-2 text-3xl font-semibold text-zinc-950">Sync dashboard</h1>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-600">
                        Local admin view for registered browsers, latest sync snapshots, history counts, and pending send-tab commands.
                    </p>
                </div>
                <div class="rounded-lg border border-amber-300 bg-amber-50 px-4 py-3 text-sm text-amber-950">
                    History sync is opt-in only. Browsing history can reveal sensitive private information.
                </div>
            </header>

            <section class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Devices</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $devices->count() }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">History items</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $historyItemCount }}</p>
                </div>
                <div class="rounded-lg border border-zinc-200 bg-white p-5">
                    <p class="text-sm text-zinc-500">Pending tab commands</p>
                    <p class="mt-2 text-3xl font-semibold">{{ $pendingCommandCount }}</p>
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
        </main>
    </body>
</html>
