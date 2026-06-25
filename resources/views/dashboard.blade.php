<x-dashboard.layout title="BrowserBridge Dashboard">
    <x-dashboard.sidebar>
        <x-dashboard.sidebar-item href="#overview" :active="true" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>'>Overview</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#devices" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/></svg>'>Devices</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#tab-handoff" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg>'>Tab handoff</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#bookmark-sync" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12a9 9 0 1 1-9-9c2.52 0 4.93 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/></svg>'>Bookmark sync</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#activity" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>'>Activity</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#bookmarks" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>'>Bookmarks</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#history" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/></svg>'>History</x-dashboard.sidebar-item>
        <x-dashboard.sidebar-item href="#privacy-data" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>'>Privacy & Data</x-dashboard.sidebar-item>
    </x-dashboard.sidebar>

    <div class="flex-1 min-w-0 flex flex-col gap-10">
        <div id="overview" class="scroll-mt-8">
            <x-dashboard.header title="BrowserBridge" description="Your private bridge between browsers.">
                <x-slot:actions>
                    <x-dashboard.badge variant="accent" class="mr-2">
                        <span class="w-1.5 h-1.5 rounded-full bg-[var(--color-primary-strong)]"></span> Local server running
                    </x-dashboard.badge>
                    <x-dashboard.button onclick="openModal('setup-sync-modal')">Set up bookmark sync</x-dashboard.button>
                </x-slot:actions>
            </x-dashboard.header>
            
            @if (session('status'))
                <x-dashboard.badge variant="warning" class="mb-6 block p-3 text-sm font-medium w-full">{{ session('status') }}</x-dashboard.badge>
            @endif

            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4">
                <x-dashboard.stat-card label="Devices" value="{{ $storageCounts['devices'] }}" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M10 8h4M10 16h4"/></svg>' />
                <x-dashboard.stat-card label="Bookmarks" value="{{ $storageCounts['normalizedBookmarks'] }}" meta="{{ $storageCounts['bookmarkSnapshots'] }} snapshots" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>' />
                <x-dashboard.stat-card label="Open tabs" value="{{ $latestTabSnapshots->sum('tab_count') }}" meta="{{ $storageCounts['tabSnapshots'] }} snapshots" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg>' />
                <x-dashboard.stat-card label="History" value="{{ $storageCounts['historyItems'] }}" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/></svg>' />
                <x-dashboard.stat-card label="Sent tabs" value="{{ $devices->sum('pending_tab_commands_count') }}" meta="{{ $storageCounts['tabCommands'] }} total" icon='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>' />
            </div>
        </div>

        <x-dashboard.section id="devices" title="Connected devices" description="Browsers connected to this BrowserBridge server." class="scroll-mt-8">
            <x-slot:badge>
                <div class="flex gap-2" id="device-filters">
                    <x-dashboard.button data-filter="active" onclick="filterDevices('active')" variant="primary">Active</x-dashboard.button>
                    <x-dashboard.button data-filter="disconnected" onclick="filterDevices('disconnected')" variant="secondary">Disconnected</x-dashboard.button>
                    <x-dashboard.button data-filter="all" onclick="filterDevices('all')" variant="secondary">All</x-dashboard.button>
                </div>
            </x-slot:badge>
            
            @if ($devices->isEmpty())
                <x-dashboard.empty-state title="No devices found." description="Register a device from the Chrome or Safari extension to see it here." />
            @else
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach ($devices as $device)
                        <div data-device-card data-status="{{ $device->trashed() ? 'disconnected' : 'active' }}">
                            <x-dashboard.device-card 
                                :device="$device" 
                                :is-active="!$device->trashed()"
                                disconnectRoute="{{ route('dashboard.device.destroy', $device) }}"
                                purgeRoute="{{ route('dashboard.device.purge', $device) }}"
                            />
                        </div>
                    @endforeach
                </div>
            @endif
        </x-dashboard.section>

        <x-dashboard.section id="tab-handoff" title="Tab handoff" description="Continue anywhere with your recent open and sent tabs." class="scroll-mt-8">
            <x-dashboard.card class="p-5 flex flex-col gap-4 border-[var(--color-primary-border)]">
                <h3 class="text-base font-bold text-[var(--color-primary-text)] m-0">Recent tab handoffs</h3>
                @if ($tabCommands->isEmpty())
                    <div class="text-sm text-[var(--color-muted)]">No tabs sent recently.</div>
                @else
                    <div class="flex flex-col gap-3">
                        @foreach ($tabCommands->take(5) as $tabCommand)
                            <div class="flex items-center justify-between gap-4 p-3 rounded-[var(--radius-md)] border border-[var(--color-border)] bg-[var(--color-surface-muted)] hover:border-[var(--color-border-strong)] transition-colors">
                                <div class="flex items-center gap-3 min-w-0">
                                    <x-dashboard.favicon :url="$tabCommand->url" class="w-8 h-8" />
                                    <div class="min-w-0">
                                        <a href="{{ $tabCommand->url }}" target="_blank" rel="noreferrer" class="block text-sm font-semibold text-[var(--color-text)] truncate hover:text-[var(--color-primary)] transition-colors">{{ $tabCommand->title ?: $tabCommand->url }}</a>
                                        <div class="text-xs text-[var(--color-muted)] truncate mt-0.5">
                                            {{ $tabCommand->sourceDevice?->name ?? 'Target unavailable' }} &rarr; {{ $tabCommand->targetDevice?->name ?? 'Target unavailable' }} &middot; {{ $tabCommand->created_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                </div>
                                <x-dashboard.badge variant="{{ $tabCommand->status === \App\Enums\TabCommandStatus::Pending ? 'warning' : 'neutral' }}">
                                    {{ $tabCommand->status->value }}
                                </x-dashboard.badge>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-dashboard.card>

            <h3 class="text-base font-bold text-[var(--color-text)] mt-4">Open tabs by device</h3>
            @if ($latestTabSnapshots->isEmpty())
                <x-dashboard.empty-state title="No device tab snapshots yet." />
            @else
                <div class="grid md:grid-cols-2 gap-4">
                    @foreach ($latestTabSnapshots as $tabSnapshot)
                        @php
                            $tabs = collect($tabSnapshot->payload_json['tabs'] ?? []);
                            $visibleTabs = $tabs->take(5);
                            $hiddenTabs = $tabs->skip(5);
                        @endphp
                        <x-dashboard.card class="p-4 flex flex-col gap-3">
                            <div class="flex items-center justify-between gap-2 border-b border-[var(--color-border)] pb-2 mb-1">
                                <h4 class="font-bold text-sm text-[var(--color-text)] truncate">{{ $tabSnapshot->device?->name ?? 'Target unavailable' }}</h4>
                                <span class="text-xs text-[var(--color-muted)] shrink-0">{{ $tabSnapshot->created_at->diffForHumans() }}</span>
                            </div>
                            <div class="flex flex-col gap-0.5">
                                @foreach ($visibleTabs as $tab)
                                    <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="flex items-center gap-3 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors min-w-0">
                                        <x-dashboard.favicon :url="$tab['url'] ?? ''" class="w-6 h-6" />
                                        <div class="text-sm text-[var(--color-text)] truncate transition-colors flex-1">{{ $tab['title'] ?? $tab['url'] ?? 'Untitled Tab' }}</div>
                                    </a>
                                @endforeach
                            </div>
                            @if ($hiddenTabs->isNotEmpty())
                                <div class="text-xs text-[var(--color-muted)] italic mt-1">And {{ $hiddenTabs->count() }} more tabs...</div>
                            @endif
                        </x-dashboard.card>
                    @endforeach
                </div>
            @endif
        </x-dashboard.section>

        <x-dashboard.section id="bookmark-sync" title="Bookmark Sync" description="Profiles for Safe Folder Import, Merge, and guarded Mirror runs." class="scroll-mt-8">
            <x-slot:badge>
                <x-dashboard.badge variant="accent">{{ $storageCounts['bookmarkSyncProfiles'] }} profiles</x-dashboard.badge>
            </x-slot:badge>

            @if ($bookmarkSyncProfiles->isEmpty())
                <x-dashboard.empty-state title="No sync profiles yet." description="Create one from the Chrome extension options to preview and run imports." />
            @else
                <div class="grid grid-cols-1 gap-3">
                    @foreach ($bookmarkSyncProfiles as $profile)
                        <x-dashboard.card class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                            <div class="flex flex-col gap-1">
                                <div class="flex items-center gap-2">
                                    <span class="font-bold text-[var(--color-text)]">{{ $profile->name }}</span>
                                    <x-dashboard.badge variant="{{ $profile->is_active ? 'accent' : 'neutral' }}">{{ $profile->is_active ? 'Active' : 'Paused' }}</x-dashboard.badge>
                                    @if($profile->mode === \App\Enums\BookmarkSyncMode::Mirror)
                                        <x-dashboard.badge variant="warning">Mirror</x-dashboard.badge>
                                    @else
                                        <x-dashboard.badge variant="neutral">{{ str($profile->mode->value)->replace('_', ' ')->title() }}</x-dashboard.badge>
                                    @endif
                                </div>
                                <div class="text-sm text-[var(--color-muted)] flex items-center gap-2">
                                    <span>{{ $profile->sourceDevice?->name ?? 'Target unavailable' }}</span>
                                    <svg class="w-3 h-3 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                    <span>{{ $profile->targetDevice?->name ?? 'Target unavailable' }}</span>
                                </div>
                            </div>
                            <div class="flex flex-col sm:items-end gap-1 text-sm text-[var(--color-muted)]">
                                <div>{{ $profile->auto_sync_enabled ? 'Every ' . $profile->auto_sync_interval_minutes . ' min' : 'Manual only' }}</div>
                                <div class="text-xs">Last run: {{ $profile->last_run_at?->diffForHumans() ?? 'Never' }}</div>
                            </div>
                        </x-dashboard.card>
                    @endforeach
                </div>
            @endif

        </x-dashboard.section>

        <x-dashboard.section id="activity" title="Activity" description="Recent logs of Bookmark Sync runs." class="scroll-mt-8">
            @if ($bookmarkSyncRuns->isEmpty())
                <x-dashboard.empty-state title="No sync runs recorded yet." />
            @else
                <x-dashboard.card class="overflow-hidden">
                    <div class="divide-y divide-[var(--color-border)]">
                        @foreach ($bookmarkSyncRuns as $run)
                            <div class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-4 hover:bg-[var(--color-surface-muted)] transition-colors">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 {{ $run->status->value === 'success' ? 'bg-[var(--color-primary-subtle)] text-[var(--color-primary-text)]' : 'bg-[var(--color-warning-bg)] text-[var(--color-warning-text)]' }}">
                                        @if($run->status->value === 'success')
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 6 9 17l-5-5"/></svg>
                                        @else
                                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                                        @endif
                                    </div>
                                    <div class="flex flex-col gap-0.5">
                                        <div class="font-semibold text-sm text-[var(--color-text)]">{{ $run->profile?->name ?? 'Deleted profile' }}</div>
                                        <div class="text-xs text-[var(--color-muted)] flex items-center gap-1.5">
                                            <span>{{ $run->sourceDevice?->name ?? 'Target unavailable' }}</span>
                                            <svg class="w-2.5 h-2.5 opacity-50" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>
                                            <span>{{ $run->targetDevice?->name ?? 'Target unavailable' }}</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex flex-col sm:items-end gap-1">
                                    <div class="text-sm font-mono text-[var(--color-text)]">
                                        +{{ $run->added_count }} ~{{ $run->updated_count }} -{{ $run->deleted_count }}
                                    </div>
                                    <div class="text-xs text-[var(--color-muted)]">{{ $run->created_at?->diffForHumans() }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-dashboard.card>
            @endif
        </x-dashboard.section>

        <div class="flex flex-col gap-10">
            <x-dashboard.section 
                id="bookmarks" 
                title="Bookmarks" 
                class="scroll-mt-8"
                data-dashboard-browser 
                data-kind="bookmarks" 
                data-endpoint="{{ route('dashboard.bookmarks') }}" 
                data-limit="8"
            >
                <x-slot:badge>
                    <x-dashboard.badge variant="neutral" data-result-count>{{ $bookmarkTotal }} total</x-dashboard.badge>
                </x-slot:badge>
                <x-dashboard.card class="flex flex-col flex-1">
                    <div class="p-3 border-b border-[var(--color-border)]">
                        <x-dashboard.search-input data-search-input placeholder="Search bookmarks..." />
                    </div>
                    <div class="p-4 flex-1 flex flex-col min-h-[300px]">
                        <div data-loading class="text-sm text-[var(--color-muted)] p-4 text-center hidden">Loading bookmarks...</div>
                        <div data-error class="text-sm text-[var(--color-danger)] p-4 text-center hidden">Could not load bookmarks.</div>
                        <div data-empty class="{{ $browserBridgeBookmarks->isEmpty() ? '' : 'hidden' }} text-sm text-[var(--color-muted)] p-4 text-center">No bookmarks found.</div>
                        <div data-results class="flex flex-col gap-4">
                            @foreach ($browserBridgeBookmarks as $deviceName => $bookmarks)
                                <div data-result-group>
                                    <h5 class="text-xs font-bold text-[var(--color-muted)] uppercase tracking-wider mb-2">{{ $deviceName }}</h5>
                                    <div class="flex flex-col gap-1.5">
                                        @foreach ($bookmarks as $bookmark)
                                            <a href="{{ $bookmark->url }}" target="_blank" rel="noreferrer" class="flex items-center gap-3 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors">
                                                <x-dashboard.favicon :url="$bookmark->url" class="w-6 h-6" />
                                                <div class="min-w-0 flex-1">
                                                    <div class="text-sm font-semibold text-[var(--color-text)] truncate">{{ $bookmark->title ?: $bookmark->url }}</div>
                                                    <div class="text-xs text-[var(--color-muted)] truncate">
                                                        @if (! empty($bookmark->path_json))
                                                            {{ implode(' / ', $bookmark->path_json) }} &middot;
                                                        @endif
                                                        {{ $bookmark->url }}
                                                    </div>
                                                </div>
                                            </a>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div class="p-3 border-t border-[var(--color-border)]">
                        <x-dashboard.button data-load-more variant="secondary" class="w-full {{ $bookmarkTotal > 8 ? '' : 'hidden' }}">Show more</x-dashboard.button>
                    </div>
                </x-dashboard.card>
            </x-dashboard.section>

            <x-dashboard.section 
                id="history" 
                title="History" 
                class="scroll-mt-8"
                data-dashboard-browser 
                data-kind="history" 
                data-endpoint="{{ route('dashboard.history') }}" 
                data-limit="8"
            >
                <x-slot:badge>
                    <x-dashboard.badge variant="neutral" data-result-count>{{ $historyTotal }} total</x-dashboard.badge>
                </x-slot:badge>
                <x-dashboard.card class="flex flex-col flex-1">
                    <div class="p-3 border-b border-[var(--color-border)]">
                        <x-dashboard.search-input data-search-input placeholder="Search history..." />
                    </div>
                    <div class="p-4 flex-1 flex flex-col min-h-[300px]">
                        <div data-loading class="text-sm text-[var(--color-muted)] p-4 text-center hidden">Loading history...</div>
                        <div data-error class="text-sm text-[var(--color-danger)] p-4 text-center hidden">Could not load history.</div>
                        <div data-empty class="{{ $latestHistoryItems->isEmpty() ? '' : 'hidden' }} text-sm text-[var(--color-muted)] p-4 text-center">No history found.</div>
                        <div data-results class="flex flex-col gap-1.5">
                            @foreach ($latestHistoryItems as $historyItem)
                                <a href="{{ $historyItem->url }}" target="_blank" rel="noreferrer" class="flex items-center gap-3 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors">
                                    <x-dashboard.favicon :url="$historyItem->url" class="w-6 h-6" />
                                    <div class="min-w-0 flex-1">
                                        <div class="text-sm font-semibold text-[var(--color-text)] truncate">{{ $historyItem->title ?: $historyItem->url }}</div>
                                        <div class="text-xs text-[var(--color-muted)] truncate">
                                            {{ $historyItem->device?->name ?? 'Target unavailable' }} &middot; {{ $historyItem->visited_at?->diffForHumans() }}
                                        </div>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                    <div class="p-3 border-t border-[var(--color-border)]">
                        <x-dashboard.button data-load-more variant="secondary" class="w-full {{ $historyTotal > 8 ? '' : 'hidden' }}">Show more</x-dashboard.button>
                    </div>
                </x-dashboard.card>
            </x-dashboard.section>
        </div>

        <x-dashboard.danger-zone id="privacy-data" class="scroll-mt-8" title="Privacy controls & Data" description="Synced history is retained for {{ config('browserbridge.history_retention_days') }} days by default and is only a BrowserBridge shared view.">
            <form method="POST" action="{{ route('dashboard.history.destroy') }}" onsubmit="return confirm('Delete all synced BrowserBridge history?');">
                @csrf
                @method('DELETE')
                <x-dashboard.button type="submit" variant="danger">Delete synced history</x-dashboard.button>
            </form>
        </x-dashboard.danger-zone>
    </div>

    <!-- Bookmark Sync Setup Modal -->
    <x-dashboard.modal id="setup-sync-modal" title="Set up Bookmark Sync" description="Synchronize bookmarks between your connected devices.">
        <div class="flex flex-col gap-4 min-w-0">
            <div class="flex flex-col gap-1.5 min-w-0">
                <label class="text-sm font-semibold text-[var(--color-text)]">Source device</label>
                <select class="w-full bg-[var(--color-surface)] border border-[var(--color-border-strong)] text-[var(--color-text)] text-sm rounded-[var(--radius-md)] px-3 py-2 min-h-[38px] outline-none focus:ring-2 focus:ring-[var(--color-primary)]">
                    <option value="">Select a source device...</option>
                    @foreach($devices->filter(fn($d) => $d->capabilities()['bookmarks_read'] ?? false) as $device)
                        <option value="{{ $device->id }}">{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-0">
                <label class="text-sm font-semibold text-[var(--color-text)]">Target device</label>
                <select class="w-full bg-[var(--color-surface)] border border-[var(--color-border-strong)] text-[var(--color-text)] text-sm rounded-[var(--radius-md)] px-3 py-2 min-h-[38px] outline-none focus:ring-2 focus:ring-[var(--color-primary)]">
                    <option value="">Select a target device...</option>
                    @foreach($devices->filter(fn($d) => $d->capabilities()['bookmarks_read'] ?? false) as $device)
                        <option value="{{ $device->id }}">{{ $device->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-0">
                <label class="text-sm font-semibold text-[var(--color-text)]">Sync mode</label>
                <select class="w-full bg-[var(--color-surface)] border border-[var(--color-border-strong)] text-[var(--color-text)] text-sm rounded-[var(--radius-md)] px-3 py-2 min-h-[38px] outline-none focus:ring-2 focus:ring-[var(--color-primary)]">
                    <option value="safe_folder" selected>Safe folder (Recommended)</option>
                    <option value="merge" disabled>Merge (Coming soon)</option>
                    <option value="mirror" disabled>Mirror (Dangerous - coming soon)</option>
                </select>
            </div>
            <div class="flex flex-col gap-1.5 min-w-0">
                <label class="text-sm font-semibold text-[var(--color-text)]">Automation</label>
                <select class="w-full bg-[var(--color-surface)] border border-[var(--color-border-strong)] text-[var(--color-text)] text-sm rounded-[var(--radius-md)] px-3 py-2 min-h-[38px] outline-none focus:ring-2 focus:ring-[var(--color-primary)]">
                    <option value="manual">Manual only</option>
                    <option value="15">Every 15 minutes</option>
                    <option value="60">Hourly</option>
                    <option value="1440">Daily</option>
                </select>
            </div>
            <x-dashboard.badge variant="warning" class="mt-2 block p-3 w-full text-sm font-medium whitespace-normal break-words">
                Full profile management and merging will be fully enabled in a future update. For now, configure profiles directly from the Chrome extension.
            </x-dashboard.badge>
        </div>
        <x-slot:footer>
            <x-dashboard.button variant="ghost" onclick="closeModal('setup-sync-modal')">Cancel</x-dashboard.button>
            <x-dashboard.button disabled class="opacity-50 cursor-not-allowed">Save profile</x-dashboard.button>
        </x-slot:footer>
    </x-dashboard.modal>

    <script>
        window.filterDevices = function(status) {
            // Update button styles
            document.querySelectorAll('#device-filters [data-filter]').forEach(btn => {
                if (btn.dataset.filter === status) {
                    btn.classList.add('bg-[var(--color-primary)]', 'text-[var(--color-primary-text)]');
                    btn.classList.remove('bg-[var(--color-surface)]', 'text-[var(--color-text)]', 'hover:bg-[var(--color-surface-muted)]');
                } else {
                    btn.classList.remove('bg-[var(--color-primary)]', 'text-[var(--color-primary-text)]');
                    btn.classList.add('bg-[var(--color-surface)]', 'text-[var(--color-text)]', 'hover:bg-[var(--color-surface-muted)]');
                }
            });

            // Update card visibility
            document.querySelectorAll('[data-device-card]').forEach(card => {
                if (status === 'all' || card.dataset.status === status) {
                    card.classList.remove('hidden');
                } else {
                    card.classList.add('hidden');
                }
            });
        };

        document.addEventListener('DOMContentLoaded', () => {
            // Initial filter state based on URL or default to 'active'
            const urlParams = new URLSearchParams(window.location.search);
            const initialStatus = urlParams.get('device_status') || 'active';
            window.filterDevices(initialStatus);

            const observer = new IntersectionObserver((entries) => {
                let activeId = null;
                // Find the first intersecting entry from the top
                const intersecting = entries.filter(e => e.isIntersecting);
                if (intersecting.length > 0) {
                    activeId = intersecting[0].target.id;
                }

                if (activeId) {
                    document.querySelectorAll('[data-sidebar-item]').forEach(el => {
                        const isActive = el.getAttribute('href') === `#${activeId}`;
                        if (isActive) {
                            el.classList.add('active', 'bg-[var(--color-surface)]', 'text-[var(--color-text)]', 'shadow-[var(--shadow-sm)]');
                            el.classList.remove('text-[var(--color-muted)]', 'hover:bg-[var(--color-surface)]', 'hover:text-[var(--color-text)]', 'hover:shadow-[var(--shadow-sm)]');
                        } else {
                            el.classList.remove('active', 'bg-[var(--color-surface)]', 'text-[var(--color-text)]', 'shadow-[var(--shadow-sm)]');
                            el.classList.add('text-[var(--color-muted)]', 'hover:bg-[var(--color-surface)]', 'hover:text-[var(--color-text)]', 'hover:shadow-[var(--shadow-sm)]');
                        }
                    });
                }
            }, { rootMargin: '-10% 0px -70% 0px' });

            document.querySelectorAll('div[id="overview"], section[id]').forEach(el => observer.observe(el));
        });
    </script>
</x-dashboard.layout>
