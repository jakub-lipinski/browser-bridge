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
        <main class="bb-dashboard-layout">
            <nav class="bb-sidebar">
                <div class="bb-kicker" style="margin-bottom:0">BrowserBridge</div>
                <div class="bb-nav">
                    <a href="#overview" class="bb-nav-item active">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/></svg>
                        </span>
                        Overview
                    </a>
                    <a href="#devices" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="4" y="4" width="16" height="16" rx="2" ry="2"/><rect x="9" y="9" width="6" height="6"/></svg>
                        </span>
                        Devices
                    </a>
                    <a href="#tabs" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 20h8"/></svg>
                        </span>
                        Tabs
                    </a>
                    <a href="#bookmarks" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m19 21-7-4-7 4V5a2 2 0 0 1 2-2h10a2 2 0 0 1 2 2z"/></svg>
                        </span>
                        Bookmarks
                    </a>
                    <a href="#history" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 12a9 9 0 1 0 3-6.7"/><path d="M3 3v6h6"/><path d="M12 7v5l3 2"/></svg>
                        </span>
                        History
                    </a>
                    <a href="#activity" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                        </span>
                        Activity
                    </a>
                    <a href="#settings" class="bb-nav-item">
                        <span class="bb-icon" style="height:24px;width:24px" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                        </span>
                        Settings
                    </a>
                </div>
            </nav>

            <div class="bb-content">
                <header class="bb-header" id="overview">
                    <div>
                        <h1 class="bb-title">BrowserBridge</h1>
                        <p class="bb-copy">Your private bridge between browsers.</p>
                        <div class="bb-row" style="margin-top: 16px;">
                            <a href="#tabs" class="bb-button">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width:16px;height:16px"><path d="m22 2-7 20-4-9-9-4Z"/><path d="M22 2 11 13"/></svg>
                                Send a tab
                            </a>
                            <a href="#bookmarks" class="bb-button bb-button-secondary">Set up bookmark sync</a>
                        </div>
                    </div>
                    <span class="bb-badge bb-badge-accent">
                        <span class="bb-icon" aria-hidden="true">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>
                        </span>
                        Local server running
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

            <section id="devices">
                <div class="bb-section-head" style="margin-bottom: 16px;">
                    <div>
                        <h2 class="bb-section-title" style="font-size: 20px;">Connected devices</h2>
                        <p class="bb-section-copy">Browsers connected to this BrowserBridge server.</p>
                    </div>
                    <div class="bb-row" style="gap: 8px;">
                        <a href="{{ route('dashboard', ['status' => 'active']) }}#devices" class="bb-button {{ $deviceStatus === 'active' ? 'bb-button-accent' : 'bb-button-secondary' }}">Active</a>
                        <a href="{{ route('dashboard', ['status' => 'disconnected']) }}#devices" class="bb-button {{ $deviceStatus === 'disconnected' ? 'bb-button-accent' : 'bb-button-secondary' }}">Disconnected</a>
                        <a href="{{ route('dashboard', ['status' => 'all']) }}#devices" class="bb-button {{ $deviceStatus === 'all' ? 'bb-button-accent' : 'bb-button-secondary' }}">All</a>
                    </div>
                </div>

                @if ($devices->isEmpty())
                    <div class="bb-card bb-empty">No devices found.</div>
                @else
                    <div class="bb-grid bb-grid-2">
                        @foreach ($devices as $device)
                            @php
                                $capabilities = $device->capabilities();
                                $historyMode = $capabilities['history_mode'] ?? 'native';
                                $isFullSync = $capabilities['bookmarks_read'] && $capabilities['history_read'];
                                $isSafari = $device->browser === 'safari';
                            @endphp
                            <div class="bb-device-card">
                                <div class="bb-device-card-header">
                                    <div>
                                        <h3 class="bb-device-card-title">{{ $device->name }}</h3>
                                        <div class="bb-row" style="gap: 6px; margin-top: 8px;">
                                            <span class="bb-badge" style="background:var(--bb-surface-muted)">{{ $device->browser }}</span>
                                            <span class="bb-badge" style="background:var(--bb-surface-muted)">{{ $device->platform }}</span>
                                        </div>
                                    </div>
                                    <span class="bb-badge {{ $device->last_seen_at && $device->last_seen_at->diffInMinutes() < 10 ? 'bb-badge-accent' : '' }}">
                                        {{ $device->last_seen_at?->diffForHumans() ?? 'Never' }}
                                    </span>
                                </div>
                                
                                <div style="margin-top: 4px;">
                                    @if ($isFullSync)
                                        <span class="bb-item-title" style="color:var(--bb-accent-strong)">Full sync available</span>
                                        <div class="bb-item-meta">Tabs, bookmarks and history can sync.</div>
                                    @elseif ($isSafari)
                                        <span class="bb-item-title" style="color:var(--bb-warning-text)">Limited by Safari</span>
                                        <div class="bb-item-meta">Tabs work. Safari bookmarks/history are not exposed by Safari.</div>
                                    @else
                                        <span class="bb-item-title">Partial sync</span>
                                        <div class="bb-item-meta">Some features are disabled.</div>
                                    @endif
                                </div>

                                <div class="bb-grid bb-grid-2" style="gap:12px; margin-top:8px;">
                                    <div>
                                        <div class="bb-item-title">{{ $device->latestTabSnapshot?->tab_count ?? 0 }} open tabs</div>
                                        <div class="bb-item-meta">Pending: {{ $device->pending_tab_commands_count }}</div>
                                    </div>
                                    <div>
                                        <div class="bb-item-title">{{ $device->normalized_bookmarks_count }} bookmarks</div>
                                        <div class="bb-item-meta">{{ $device->history_items_count }} history items</div>
                                    </div>
                                </div>

                                <details class="bb-details">
                                    <summary class="bb-details-summary">
                                        <span class="bb-icon" style="height:20px;width:20px;background:none;border:none">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M12 16v-4"/><path d="M12 8h.01"/></svg>
                                        </span>
                                        Advanced details
                                    </summary>
                                    <div class="bb-details-content bb-stack" style="padding-bottom:14px; gap:12px;">
                                        <div>
                                            <div class="bb-item-title">Device ID</div>
                                            <div class="bb-mono" style="margin-top:4px">{{ $device->uuid }}</div>
                                        </div>
                                        <div>
                                            <div class="bb-item-title">Capabilities</div>
                                            <div class="bb-list" style="padding: 8px 0 0; gap: 6px;">
                                                <div class="bb-row">
                                                    <span class="bb-item-meta">Can sync native bookmarks</span>
                                                    <span class="bb-badge {{ $capabilities['bookmarks_read'] ? 'bb-badge-accent' : 'bb-badge-warning' }}">{{ $capabilities['bookmarks_read'] ? 'Available' : 'Not available' }}</span>
                                                </div>
                                                <div class="bb-row">
                                                    <span class="bb-item-meta">Can sync native history</span>
                                                    <span class="bb-badge {{ $capabilities['history_read'] ? 'bb-badge-accent' : 'bb-badge-warning' }}">{{ $capabilities['history_read'] ? 'Available' : ($historyMode === 'activity' ? 'Activity only' : 'Not available') }}</span>
                                                </div>
                                                <div class="bb-row">
                                                    <span class="bb-item-meta">Can send tabs</span>
                                                    <span class="bb-badge bb-badge-accent">Available</span>
                                                </div>
                                                <div class="bb-row">
                                                    <span class="bb-item-meta">Can receive tabs</span>
                                                    <span class="bb-badge bb-badge-accent">Available</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="bb-row" style="margin-top:8px;">
                                            @if($device->trashed())
                                                <span class="bb-badge bb-badge-warning">Disconnected on {{ $device->disconnected_at?->format('M d, Y') }}</span>
                                            @else
                                                <form method="POST" action="{{ route('dashboard.device.destroy', $device) }}" onsubmit="return confirm('Disconnect this device? It will disappear from active devices and pending tab commands will be cancelled. Existing synced data will be kept.');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="bb-button bb-button-danger" style="min-height:30px;font-size:12px;padding:4px 8px;">Disconnect</button>
                                                </form>
                                            @endif
                                        </div>

                                        <div style="margin-top: 16px; padding-top: 16px; border-top: 1px solid var(--bb-border);">
                                            <h4 class="bb-item-title" style="color:var(--bb-warning-text)">Purge device data</h4>
                                            <p class="bb-item-meta" style="margin-top:4px;">This permanently deletes this device and its related BrowserBridge data. This cannot be undone.</p>
                                            
                                            <div class="bb-list" style="margin-top:8px; padding:8px 0; gap:4px; font-size:11px;">
                                                <div>• {{ $device->normalized_bookmarks_count }} bookmarks</div>
                                                <div>• {{ $device->history_items_count }} history items</div>
                                                <div>• {{ $device->tab_snapshots_count }} tab snapshots</div>
                                                <div>• {{ $device->sent_tab_commands_count + $device->incoming_tab_commands_count }} tab commands</div>
                                                <div>• {{ $device->bookmark_sync_profiles_as_source_count + $device->bookmark_sync_profiles_as_target_count }} sync profiles</div>
                                            </div>

                                            <form method="POST" action="{{ route('dashboard.device.purge', $device) }}" style="margin-top:12px; display:flex; flex-direction:column; gap:8px;">
                                                @csrf
                                                @method('DELETE')
                                                <input type="text" name="confirmation_text" placeholder="Type DELETE DEVICE DATA" required style="font-size:12px; padding:6px 8px; border:1px solid var(--bb-border); border-radius:6px; background:var(--bb-surface); color:var(--bb-text);">
                                                <button type="submit" class="bb-button" style="background:var(--bb-warning-text); color:white; min-height:30px; font-size:12px; padding:4px 8px; justify-content:center;">Purge Permanently</button>
                                            </form>
                                        </div>
                                    </div>
                                </details>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <section id="activity" class="bb-card" style="margin-bottom: 24px;">
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

            <section id="tabs">
                <div style="margin-bottom: 16px;">
                    <h2 class="bb-section-title" style="font-size: 20px;">Tabs</h2>
                    <p class="bb-section-copy">Continue anywhere with your recent open and sent tabs.</p>
                </div>

                <div class="bb-hero-card" style="margin-bottom: 24px;">
                    <h3 class="bb-section-title" style="color:var(--bb-accent-strong)">Recent tab handoffs</h3>
                    
                    @if ($tabCommands->isEmpty())
                        <div class="bb-empty" style="margin-top: 16px;">No tabs sent recently.</div>
                    @else
                        <div class="bb-list" style="margin-top: 16px; background:var(--bb-surface); border-radius:var(--bb-radius-md); padding:12px;">
                            @foreach ($tabCommands->take(5) as $tabCommand)
                                @php
                                    $domain = parse_url($tabCommand->url, PHP_URL_HOST) ?? '';
                                    $firstLetter = $domain ? strtoupper(substr(str_replace('www.', '', $domain), 0, 1)) : '?';
                                @endphp
                                <div class="bb-row" style="align-items: center; justify-content: space-between; border-bottom: 1px solid var(--bb-border); padding-bottom: 12px; margin-bottom: 12px;">
                                    <div class="bb-row" style="gap: 12px; flex: 1; min-width: 0;">
                                        <div class="bb-favicon-fallback">{{ $firstLetter }}</div>
                                        <div style="min-width: 0;">
                                            <a href="{{ $tabCommand->url }}" target="_blank" rel="noreferrer" class="bb-item-title" style="display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                {{ $tabCommand->title ?: $tabCommand->url }}
                                            </a>
                                            <div class="bb-item-meta" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                {{ $tabCommand->sourceDevice?->name ?? 'Unknown source' }} &rarr; {{ $tabCommand->targetDevice?->name ?? 'Unknown target' }} &middot; {{ $tabCommand->created_at?->diffForHumans() }}
                                            </div>
                                        </div>
                                    </div>
                                    <span class="bb-badge {{ $tabCommand->status === \App\Enums\TabCommandStatus::Pending ? 'bb-badge-warning' : '' }}">
                                        {{ $tabCommand->status->value }}
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                <h3 class="bb-section-title" style="margin-bottom: 16px;">Open tabs by device</h3>
                @if ($latestTabSnapshots->isEmpty())
                    <div class="bb-card bb-empty">No device tab snapshots yet.</div>
                @else
                    <div class="bb-grid bb-grid-2">
                        @foreach ($latestTabSnapshots as $tabSnapshot)
                            @php
                                $tabs = collect($tabSnapshot->payload_json['tabs'] ?? []);
                                $visibleTabs = $tabs->take(5);
                                $hiddenTabs = $tabs->skip(5);
                            @endphp
                            <div class="bb-card" style="padding: 16px;">
                                <div class="bb-row" style="margin-bottom: 12px;">
                                    <h3 class="bb-item-title">{{ $tabSnapshot->device?->name ?? 'Unknown device' }}</h3>
                                    <span class="bb-item-meta">{{ $tabSnapshot->created_at->diffForHumans() }}</span>
                                </div>
                                <div class="bb-stack">
                                    @foreach ($visibleTabs as $tab)
                                        @php
                                            $domain = parse_url($tab['url'] ?? '', PHP_URL_HOST) ?? '';
                                            $firstLetter = $domain ? strtoupper(substr(str_replace('www.', '', $domain), 0, 1)) : '?';
                                        @endphp
                                        <div class="bb-row" style="gap: 8px;">
                                            <div class="bb-favicon-fallback">{{ $firstLetter }}</div>
                                            <div style="min-width:0; flex:1;">
                                                <a href="{{ $tab['url'] ?? '#' }}" target="_blank" rel="noreferrer" class="bb-item-title" style="font-weight: 500; display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                                    {{ $tab['title'] ?? $tab['url'] ?? 'Untitled Tab' }}
                                                </a>
                                            </div>
                                        </div>
                                    @endforeach
                                    @if ($hiddenTabs->isNotEmpty())
                                        <div class="bb-item-meta">And {{ $hiddenTabs->count() }} more tabs...</div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>

            <div class="bb-two-column">
                <section
                    id="bookmarks"
                    class="bb-card"
                    data-dashboard-browser
                    data-kind="bookmarks"
                    data-endpoint="{{ route('dashboard.bookmarks') }}"
                    data-limit="12"
                >
                    <div class="bb-section-head" style="margin-bottom: 8px;">
                        <div>
                            <h2 class="bb-section-title">Bookmarks</h2>
                            <p class="bb-section-copy">Latest 12 by default.</p>
                        </div>
                        <span class="bb-badge" data-result-count>{{ $bookmarkTotal }} total</span>
                    </div>
                    <div class="bb-card-pad" style="padding-top: 0;">
                        <input data-search-input type="search" class="bb-input" placeholder="Search bookmarks...">
                    </div>
                    <div data-loading class="bb-empty hidden">Loading bookmarks...</div>
                    <div data-error class="bb-empty hidden">Could not load bookmarks.</div>
                    <div data-empty class="{{ $browserBridgeBookmarks->isEmpty() ? '' : 'hidden' }} bb-empty">No BrowserBridge bookmarks found.</div>
                    <div data-results class="bb-list">
                        @foreach ($browserBridgeBookmarks as $deviceName => $bookmarks)
                            <div data-result-group>
                                <h3 class="bb-section-title" style="margin-bottom: 8px;">{{ $deviceName }}</h3>
                                <div class="bb-list">
                                    @foreach ($bookmarks as $bookmark)
                                        @php
                                            $domain = parse_url($bookmark->url, PHP_URL_HOST) ?? '';
                                            $firstLetter = $domain ? strtoupper(substr(str_replace('www.', '', $domain), 0, 1)) : '?';
                                        @endphp
                                        <a href="{{ $bookmark->url }}" class="bb-list-item" target="_blank" rel="noreferrer" style="display:flex; align-items:center; gap:12px; padding:8px; border-radius:var(--bb-radius-sm);">
                                            <div class="bb-favicon-fallback">{{ $firstLetter }}</div>
                                            <div style="min-width:0; flex:1;">
                                                <div class="bb-item-title" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $bookmark->title ?: $bookmark->url }}</div>
                                                <div class="bb-item-meta" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
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
                    <div class="bb-card-pad">
                        <button data-load-more type="button" class="{{ $bookmarkTotal > 12 ? '' : 'hidden' }} bb-button bb-button-secondary">Show more</button>
                    </div>
                </section>

                <section
                    id="history"
                    class="bb-card"
                    data-dashboard-browser
                    data-kind="history"
                    data-endpoint="{{ route('dashboard.history') }}"
                    data-limit="10"
                >
                    <div class="bb-section-head" style="margin-bottom: 8px;">
                        <div>
                            <h2 class="bb-section-title">History</h2>
                            <p class="bb-section-copy">Recent 10 by default.</p>
                        </div>
                        <span class="bb-badge" data-result-count>{{ $historyTotal }} total</span>
                    </div>
                    <div class="bb-card-pad" style="padding-top: 0;">
                        <input data-search-input type="search" class="bb-input" placeholder="Search history...">
                    </div>
                    <div data-loading class="bb-empty hidden">Loading history...</div>
                    <div data-error class="bb-empty hidden">Could not load history.</div>
                    <div data-empty class="{{ $latestHistoryItems->isEmpty() ? '' : 'hidden' }} bb-empty">No BrowserBridge history found.</div>
                    <div data-results class="bb-list">
                        @foreach ($latestHistoryItems as $historyItem)
                            @php
                                $domain = parse_url($historyItem->url, PHP_URL_HOST) ?? '';
                                $firstLetter = $domain ? strtoupper(substr(str_replace('www.', '', $domain), 0, 1)) : '?';
                            @endphp
                            <a href="{{ $historyItem->url }}" target="_blank" rel="noreferrer" class="bb-list-item" style="display:flex; align-items:center; gap:12px; padding:8px; border-radius:var(--bb-radius-sm);">
                                <div class="bb-favicon-fallback">{{ $firstLetter }}</div>
                                <div style="min-width:0; flex:1;">
                                    <div class="bb-item-title" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">{{ $historyItem->title ?: $historyItem->url }}</div>
                                    <div class="bb-item-meta" style="overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                        {{ $historyItem->device?->name ?? 'Unknown device' }} &middot; {{ $historyItem->visited_at?->diffForHumans() }}
                                    </div>
                                </div>
                            </a>
                        @endforeach
                    </div>
                    <div class="bb-card-pad">
                        <button data-load-more type="button" class="{{ $historyTotal > 10 ? '' : 'hidden' }} bb-button bb-button-secondary">Show more</button>
                    </div>
                </section>
            </div>

            <section id="settings" class="bb-card bb-danger-zone" style="margin-top: 40px;">
                <div class="bb-section-head">
                    <div>
                        <h2 class="bb-section-title">Privacy controls & Settings</h2>
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
            </div>
        </main>
    </body>
</html>
