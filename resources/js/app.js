const dashboardBrowsers = document.querySelectorAll('[data-dashboard-browser]');

function escapeHtml(value) {
    const element = document.createElement('div');
    element.textContent = value ?? '';

    return element.innerHTML;
}

function formatDate(value) {
    if (!value) {
        return 'Unknown date';
    }

    return new Intl.DateTimeFormat(undefined, {
        dateStyle: 'medium',
        timeStyle: 'short',
    }).format(new Date(value));
}

function groupByDevice(items) {
    return items.reduce((groups, item) => {
        const deviceName = item.device?.name || 'Unknown device';
        groups[deviceName] = groups[deviceName] || [];
        groups[deviceName].push(item);

        return groups;
    }, {});
}

function renderBookmarkResults(items) {
    const groups = groupByDevice(items);

    return Object.entries(groups).map(([deviceName, bookmarks]) => `
        <div data-result-group>
            <h5 class="text-xs font-bold text-[var(--color-muted)] uppercase tracking-wider mb-2">${escapeHtml(deviceName)}</h5>
            <div class="flex flex-col gap-1.5">
                ${bookmarks.map((bookmark) => {
                    const domain = new URL(bookmark.url).hostname || '';
                    const firstLetter = domain ? domain.replace('www.', '').charAt(0).toUpperCase() : '?';
                    return `
                    <a href="${escapeHtml(bookmark.url)}" class="flex items-center gap-3 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors" target="_blank" rel="noreferrer">
                        <div class="w-6 h-6 shrink-0 rounded bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-[10px] font-bold text-[var(--color-muted)] uppercase">${firstLetter}</div>
                        <div class="min-w-0 flex-1">
                            <div class="text-sm font-semibold text-[var(--color-text)] truncate">${escapeHtml(bookmark.title || bookmark.url || 'Untitled bookmark')}</div>
                            <div class="text-xs text-[var(--color-muted)] truncate">
                                ${(bookmark.path || []).length > 0 ? escapeHtml(bookmark.path.join(' / ')) + ' &middot; ' : ''}
                                ${escapeHtml(bookmark.url || '')}
                            </div>
                        </div>
                    </a>
                `}).join('')}
            </div>
        </div>
    `).join('');
}

function renderHistoryResults(items) {
    return items.map((historyItem) => {
        const domain = new URL(historyItem.url).hostname || '';
        const firstLetter = domain ? domain.replace('www.', '').charAt(0).toUpperCase() : '?';
        return `
        <a href="${escapeHtml(historyItem.url)}" target="_blank" rel="noreferrer" class="flex items-center gap-3 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors">
            <div class="w-6 h-6 shrink-0 rounded bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-[10px] font-bold text-[var(--color-muted)] uppercase">${firstLetter}</div>
            <div class="min-w-0 flex-1">
                <div class="text-sm font-semibold text-[var(--color-text)] truncate">${escapeHtml(historyItem.title || historyItem.url || 'Untitled history item')}</div>
                <div class="text-xs text-[var(--color-muted)] truncate">
                    ${escapeHtml(historyItem.device?.name || 'Unknown device')} &middot; ${escapeHtml(formatDate(historyItem.visited_at))}
                </div>
            </div>
        </a>
    `}).join('');
}

function initializeDashboardBrowser(section) {
    const endpoint = section.dataset.endpoint;
    const kind = section.dataset.kind;
    const limit = Number(section.dataset.limit || 10);
    const input = section.querySelector('[data-search-input]');
    const results = section.querySelector('[data-results]');
    const loading = section.querySelector('[data-loading]');
    const error = section.querySelector('[data-error]');
    const empty = section.querySelector('[data-empty]');
    const count = section.querySelector('[data-result-count]');
    const loadMore = section.querySelector('[data-load-more]');

    if (!endpoint || !kind || !input || !results || !loading || !error || !empty || !count || !loadMore) {
        return;
    }

    let query = '';
    let offset = 0;
    let total = Number.parseInt(count.textContent || '0', 10) || 0;
    let debounceTimer;
    let requestId = 0;

    const render = (items, append = false) => {
        if (!append) {
            results.innerHTML = '';
        }

        const html = kind === 'bookmarks' ? renderBookmarkResults(items) : renderHistoryResults(items);
        results.insertAdjacentHTML('beforeend', html);
        empty.classList.toggle('hidden', results.children.length > 0);
    };

    const load = async (append = false) => {
        const currentRequestId = ++requestId;
        const url = new URL(endpoint, window.location.origin);
        url.searchParams.set('query', query);
        url.searchParams.set('offset', String(append ? offset : 0));
        url.searchParams.set('limit', String(limit));

        loading.classList.remove('hidden');
        error.classList.add('hidden');

        try {
            const response = await fetch(url, {
                headers: {
                    Accept: 'application/json',
                },
            });

            if (!response.ok) {
                throw new Error(`Request failed with status ${response.status}.`);
            }

            const payload = await response.json();

            if (currentRequestId !== requestId) {
                return;
            }

            total = payload.total;
            offset = (append ? offset : 0) + payload.data.length;
            count.textContent = `${total} ${total === 1 ? 'result' : 'results'}`;
            render(payload.data, append);
            loadMore.classList.toggle('hidden', !payload.has_more);
        } catch {
            error.classList.remove('hidden');
        } finally {
            if (currentRequestId === requestId) {
                loading.classList.add('hidden');
            }
        }
    };

    input.addEventListener('input', () => {
        window.clearTimeout(debounceTimer);
        query = input.value.trim();
        offset = 0;

        debounceTimer = window.setTimeout(() => {
            void load(false);
        }, 250);
    });

    loadMore.addEventListener('click', () => {
        void load(true);
    });
}

dashboardBrowsers.forEach(initializeDashboardBrowser);
