<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>BrowserBridge Dashboard</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body>
        <main class="bb-shell bb-stack">
            <header class="bb-header">
                <div>
                    <p class="bb-kicker">BrowserBridge local cloud</p>
                    <h1 class="bb-title">Sync dashboard</h1>
                    <p class="bb-copy">
                        A private, local view of cross-browser tab handoff, synced bookmarks, shared history, and connected devices.
                    </p>
                </div>
                <span class="bb-badge bb-badge-warning">
                    <span class="bb-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="11" width="16" height="9" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>
                    </span>
                    Local/private build
                </span>
            </header>

            @if (session('status'))
                <div class="bb-warning">
                    {{ session('status') }}
                </div>
            @endif

            <section class="bb-grid bb-grid-5" aria-label="Storage summary">
                <article class="bb-stat">
                    <div class="bb-stat-top">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="6" y="4" width="12" height="16" rx="2"/><path d="M10 8h4M10 16h4"/></svg>
                        </span>
                        <p class="bb-stat-label">Devices</p>
                    </div>
                    <p class="bb-stat-value">{{ $storageCounts['devices'] }}</p>
                </article>
                <article class="bb-stat">
                    <div class="bb-stat-top">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </span>
                        <p class="bb-stat-label">Bookmarks</p>
                    </div>
                    <p class="bb-stat-value">{{ $storageCounts['normalizedBookmarks'] }}</p>
                    <p class="bb-stat-meta">{{ $storageCounts['bookmarkSnapshots'] }} snapshots</p>
                </article>
                <article class="bb-stat">
                    <div class="bb-stat-top">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg>
                        </span>
                        <p class="bb-stat-label">Open tabs</p>
                    </div>
                    <p class="bb-stat-value">{{ $latestTabSnapshots->sum('tab_count') }}</p>
                    <p class="bb-stat-meta">{{ $storageCounts['tabSnapshots'] }} snapshots</p>
                </article>
                <article class="bb-stat">
                    <div class="bb-stat-top">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        <p class="bb-stat-label">History items</p>
                    </div>
                    <p class="bb-stat-value">{{ $storageCounts['historyItems'] }}</p>
                </article>
                <article class="bb-stat">
                    <div class="bb-stat-top">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                        </span>
                        <p class="bb-stat-label">Tab commands</p>
                    </div>
                    <p class="bb-stat-value">{{ $devices->sum('pending_tab_commands_count') }}</p>
                    <p class="bb-stat-meta">{{ $storageCounts['tabCommands'] }} total</p>
                </article>
            </section>

            <section class="bb-card">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Registered devices</h2>
                        <p class="bb-section-copy">Browsers connected to this BrowserBridge server.</p>
                    </div>
                </div>

                @if ($devices->isEmpty())
                    <div class="bb-empty">No devices have registered yet.</div>
                @else
                    <div class="bb-table-wrap">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Browser</th>
                                    <th>Platform</th>
                                    <th>Capabilities</th>
                                    <th>Last seen</th>
                                    <th>Latest tabs</th>
                                    <th>Bookmarks</th>
                                    <th>History</th>
                                    <th>Pending</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($devices as $device)
                                    @php
                                        $capabilities = $device->capabilities();
                                        $historyMode = $capabilities['history_mode'] ?? 'native';
                                    @endphp
                                    <tr>
                                        <td>
                                            <div class="bb-item-title">{{ $device->name }}</div>
                                            <div class="bb-mono">{{ $device->uuid }}</div>
                                        </td>
                                        <td><span class="bb-badge">{{ $device->browser }}</span></td>
                                        <td><span class="bb-badge">{{ $device->platform }}</span></td>
                                        <td>
                                            <div class="bb-list">
                                                <span class="bb-badge {{ $capabilities['bookmarks_read'] ? 'bb-badge-accent' : 'bb-badge-warning' }}">
                                                    Bookmark upload: {{ $capabilities['bookmarks_read'] ? 'Available' : 'Not available' }}
                                                </span>
                                                <span class="bb-badge {{ $capabilities['history_read'] ? 'bb-badge-accent' : 'bb-badge-warning' }}">
                                                    History upload:
                                                    @if ($capabilities['history_read'])
                                                        Available
                                                    @elseif ($historyMode === 'activity')
                                                        Activity only
                                                    @else
                                                        Not available
                                                    @endif
                                                </span>
                                                <span class="bb-badge bb-badge-accent">Tab sending: Available</span>
                                                <span class="bb-badge bb-badge-accent">Tab receiving: Available</span>
                                            </div>
                                        </td>
                                        <td>{{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td>{{ $device->latestTabSnapshot?->tab_count ?? 0 }}</td>
                                        <td>
                                            {{ $device->normalized_bookmarks_count }}
                                            @if ($device->browser === 'safari' && ! $capabilities['bookmarks_read'])
                                                <div class="bb-item-meta">Unsupported by Safari API</div>
                                            @endif
                                        </td>
                                        <td>
                                            {{ $device->history_items_count }}
                                            @if ($device->browser === 'safari' && $historyMode === 'activity')
                                                <div class="bb-item-meta">Activity only</div>
                                            @elseif ($device->browser === 'safari' && ! $capabilities['history_read'])
                                                <div class="bb-item-meta">Unsupported by Safari API</div>
                                            @endif
                                        </td>
                                        <td>{{ $device->pending_tab_commands_count }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="bb-card">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Bookmark Sync</h2>
                        <p class="bb-section-copy">Profiles for Safe Folder Import, Merge, and guarded Mirror runs.</p>
                    </div>
                    <span class="bb-badge bb-badge-accent">{{ $storageCounts['bookmarkSyncProfiles'] }} profiles</span>
                </div>

                @if ($bookmarkSyncProfiles->isEmpty())
                    <div class="bb-empty">
                        No bookmark sync profiles yet. Create one from the Chrome extension options to preview and run imports.
                    </div>
                @else
                    <div class="bb-table-wrap">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th>Profile</th>
                                    <th>Source</th>
                                    <th>Target</th>
                                    <th>Mode</th>
                                    <th>Scope</th>
                                    <th>Auto sync</th>
                                    <th>Last run</th>
                                    <th>Next run</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bookmarkSyncProfiles as $profile)
                                    <tr>
                                        <td>
                                            <div class="bb-item-title">{{ $profile->name }}</div>
                                            <div class="bb-item-meta">{{ $profile->is_active ? 'Active' : 'Paused' }}</div>
                                        </td>
                                        <td>{{ $profile->sourceDevice?->name ?? 'Unknown source' }}</td>
                                        <td>{{ $profile->targetDevice?->name ?? 'Unknown target' }}</td>
                                        <td>
                                            <span class="bb-badge {{ $profile->mode === \App\Enums\BookmarkSyncMode::Mirror ? 'bb-badge-warning' : '' }}">
                                                {{ str($profile->mode->value)->replace('_', ' ')->title() }}
                                            </span>
                                        </td>
                                        <td>{{ str($profile->target_scope->value)->replace('_', ' ')->title() }}</td>
                                        <td>
                                            @if ($profile->auto_sync_enabled)
                                                Every {{ $profile->auto_sync_interval_minutes }} min
                                            @else
                                                Manual only
                                            @endif
                                        </td>
                                        <td>{{ $profile->last_run_at?->diffForHumans() ?? 'Never' }}</td>
                                        <td>{{ $profile->next_run_at?->diffForHumans() ?? 'Not scheduled' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif

                <div class="bb-section-head bb-card-pad">
                    <div>
                        <h3 class="bb-section-title">Recent bookmark sync runs</h3>
                        <p class="bb-section-copy">Run history keeps previews, counts, errors, and operation logs auditable.</p>
                    </div>
                    <span class="bb-badge">{{ $storageCounts['bookmarkSyncRuns'] }} total</span>
                </div>

                @if ($bookmarkSyncRuns->isEmpty())
                    <div class="bb-empty">No bookmark sync runs recorded yet.</div>
                @else
                    <div class="bb-table-wrap">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th>Run</th>
                                    <th>Status</th>
                                    <th>Mode</th>
                                    <th>Source</th>
                                    <th>Target</th>
                                    <th>Changes</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bookmarkSyncRuns as $run)
                                    <tr>
                                        <td>{{ $run->profile?->name ?? 'Deleted profile' }}</td>
                                        <td><span class="bb-badge">{{ $run->status->value }}</span></td>
                                        <td>{{ str($run->mode->value)->replace('_', ' ')->title() }}</td>
                                        <td>{{ $run->sourceDevice?->name ?? 'Unknown source' }}</td>
                                        <td>{{ $run->targetDevice?->name ?? 'Unknown target' }}</td>
                                        <td>
                                            +{{ $run->added_count }}
                                            / ~{{ $run->updated_count }}
                                            / &rarr;{{ $run->moved_count }}
                                            / -{{ $run->deleted_count }}
                                            / skipped {{ $run->skipped_count }}
                                        </td>
                                        <td>{{ $run->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="bb-card">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Pending and recent tab commands</h2>
                        <p class="bb-section-copy">Tab handoff is the main BrowserBridge workflow, so command status stays visible.</p>
                    </div>
                    <span class="bb-badge bb-badge-accent">{{ $devices->sum('pending_tab_commands_count') }} pending</span>
                </div>

                @if ($tabCommands->isEmpty())
                    <div class="bb-empty">No tab commands yet.</div>
                @else
                    <div class="bb-table-wrap">
                        <table class="bb-table">
                            <thead>
                                <tr>
                                    <th>Tab</th>
                                    <th>Source</th>
                                    <th>Target</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($tabCommands as $tabCommand)
                                    <tr>
                                        <td>
                                            <a href="{{ $tabCommand->url }}" target="_blank" rel="noreferrer" class="bb-item-title">
                                                {{ $tabCommand->title ?: $tabCommand->url }}
                                            </a>
                                            <div class="bb-item-meta">{{ $tabCommand->url }}</div>
                                        </td>
                                        <td>{{ $tabCommand->sourceDevice?->name ?? 'Unknown source' }}</td>
                                        <td>{{ $tabCommand->targetDevice?->name ?? 'Unknown target' }}</td>
                                        <td>
                                            <span class="bb-badge {{ $tabCommand->status === \App\Enums\TabCommandStatus::Pending ? 'bb-badge-warning' : '' }}">
                                                {{ $tabCommand->status->value }}
                                            </span>
                                        </td>
                                        <td>{{ $tabCommand->created_at?->diffForHumans() }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="bb-card">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Latest open tabs</h2>
                        <p class="bb-section-copy">Most recent tab snapshot per device, capped to five visible tabs each.</p>
                    </div>
                </div>

                @if ($latestTabSnapshots->isEmpty())
                    <div class="bb-empty">No open tab snapshots stored.</div>
                @else
                    <div class="bb-grid bb-grid-2 bb-card-pad">
                        @foreach ($latestTabSnapshots as $tabSnapshot)
                            @php
                                $tabs = collect($tabSnapshot->payload_json['tabs'] ?? []);
                                $visibleTabs = $tabs->take(5);
                                $hiddenTabs = $tabs->skip(5);
                            @endphp
                            <article class="bb-device-panel">
                                <div class="bb-section-head">
                                    <div>
                                        <h3 class="bb-section-title">{{ $tabSnapshot->device?->name ?? 'Unknown device' }}</h3>
                                        <p class="bb-section-copy">{{ $tabSnapshot->tab_count }} tabs - {{ $tabSnapshot->created_at?->diffForHumans() }}</p>
                                    </div>
                                </div>
                                <div class="bb-list">
                                    @foreach ($visibleTabs as $tab)
                                        <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="bb-list-item">
                                            <div class="bb-item-title">{{ $tab['title'] ?? $tab['url'] ?? 'Untitled tab' }}</div>
                                            <div class="bb-item-meta">{{ $tab['url'] ?? '' }}</div>
                                        </a>
                                    @endforeach
                                    @if ($hiddenTabs->isNotEmpty())
                                        <details>
                                            <summary class="bb-button bb-button-secondary">Show {{ $hiddenTabs->count() }} more</summary>
                                            <div class="bb-list">
                                                @foreach ($hiddenTabs as $tab)
                                                    <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="bb-list-item">
                                                        <div class="bb-item-title">{{ $tab['title'] ?? $tab['url'] ?? 'Untitled tab' }}</div>
                                                        <div class="bb-item-meta">{{ $tab['url'] ?? '' }}</div>
                                                    </a>
                                                @endforeach
                                            </div>
                                        </details>
                                    @endif
                                </div>
                            </article>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="bb-two-column">
                <section
                    class="bb-card"
                    data-dashboard-browser
                    data-kind="bookmarks"
                    data-endpoint="{{ route('dashboard.bookmarks') }}"
                    data-limit="12"
                >
                    <div class="bb-section-head">
                        <div>
                            <h2 class="bb-section-title">BrowserBridge Bookmarks</h2>
                            <p class="bb-section-copy">Latest 12 by default. Search updates while typing.</p>
                        </div>
                        <span class="bb-badge" data-result-count>{{ $bookmarkTotal }} total</span>
                    </div>
                    <div class="bb-card-pad">
                        <input data-search-input type="search" class="bb-input" placeholder="Search bookmarks">
                    </div>
                    <div data-loading class="bb-empty hidden">Loading bookmarks...</div>
                    <div data-error class="bb-empty hidden">Could not load bookmarks.</div>
                    <div data-empty class="{{ $browserBridgeBookmarks->isEmpty() ? '' : 'hidden' }} bb-empty">No BrowserBridge bookmarks found.</div>
                    <div data-results class="bb-list">
                        @foreach ($browserBridgeBookmarks as $deviceName => $bookmarks)
                            <div data-result-group>
                                <h3 class="bb-section-title">{{ $deviceName }}</h3>
                                <div class="bb-list">
                                    @foreach ($bookmarks as $bookmark)
                                        <a href="{{ $bookmark->url }}" class="bb-list-item" target="_blank" rel="noreferrer">
                                            <div class="bb-item-title">{{ $bookmark->title ?: $bookmark->url }}</div>
                                            <div class="bb-item-meta">{{ $bookmark->url }}</div>
                                            @if (! empty($bookmark->path_json))
                                                <div class="bb-item-meta">{{ implode(' / ', $bookmark->path_json) }}</div>
                                            @endif
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                    <div class="bb-card-pad">
                        <button data-load-more type="button" class="{{ $bookmarkTotal > 12 ? '' : 'hidden' }} bb-button bb-button-secondary">Show more</button>
                    </div>
                </section>

                <section
                    class="bb-card"
                    data-dashboard-browser
                    data-kind="history"
                    data-endpoint="{{ route('dashboard.history') }}"
                    data-limit="10"
                >
                    <div class="bb-section-head">
                        <div>
                            <h2 class="bb-section-title">BrowserBridge History</h2>
                            <p class="bb-section-copy">Recent 10 by default. Search updates while typing.</p>
                        </div>
                        <span class="bb-badge" data-result-count>{{ $historyTotal }} total</span>
                    </div>
                    <div class="bb-card-pad">
                        <input data-search-input type="search" class="bb-input" placeholder="Search history">
                    </div>
                    <div data-loading class="bb-empty hidden">Loading history...</div>
                    <div data-error class="bb-empty hidden">Could not load history.</div>
                    <div data-empty class="{{ $latestHistoryItems->isEmpty() ? '' : 'hidden' }} bb-empty">No BrowserBridge history found.</div>
                    <div data-results class="bb-list">
                        @foreach ($latestHistoryItems as $historyItem)
                            <a href="{{ $historyItem->url }}" target="_blank" rel="noreferrer" class="bb-list-item">
                                <div class="bb-item-title">{{ $historyItem->title ?: $historyItem->url }}</div>
                                <div class="bb-item-meta">{{ $historyItem->url }}</div>
                                <div class="bb-item-meta">
                                    {{ $historyItem->device?->name ?? 'Unknown device' }} - {{ $historyItem->visited_at?->diffForHumans() }}
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div class="bb-card-pad">
                        <button data-load-more type="button" class="{{ $historyTotal > 10 ? '' : 'hidden' }} bb-button bb-button-secondary">Show more</button>
                    </div>
                </section>
            </div>

            <section class="bb-card bb-danger-zone">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Privacy controls</h2>
                        <p class="bb-section-copy">
                            Synced history is retained for {{ config('browserbridge.history_retention_days') }} days by default and is only a BrowserBridge shared view.
                        </p>
                    </div>
                    <form method="POST" action="{{ route('dashboard.history.destroy') }}" onsubmit="return confirm('Delete all synced BrowserBridge history?');">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="bb-button bb-button-danger">Delete synced history</button>
                    </form>
                </div>
            </section>
        </main>
    </body>
</html>
