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
        <div class="px-5 py-4" data-result-group>
            <h3 class="text-sm font-semibold text-zinc-950">${escapeHtml(deviceName)}</h3>
            <div class="mt-3 grid gap-2">
                ${bookmarks.map((bookmark) => `
                    <a href="${escapeHtml(bookmark.url)}" class="rounded-md border border-zinc-100 px-3 py-2 hover:border-teal-600" target="_blank" rel="noreferrer">
                        <div class="truncate text-sm font-medium text-zinc-950">${escapeHtml(bookmark.title || bookmark.url || 'Untitled bookmark')}</div>
                        <div class="mt-1 truncate text-xs text-zinc-500">${escapeHtml(bookmark.url || '')}</div>
                        ${(bookmark.path || []).length > 0 ? `<div class="mt-1 truncate text-xs text-zinc-400">${escapeHtml(bookmark.path.join(' / '))}</div>` : ''}
                    </a>
                `).join('')}
            </div>
        </div>
    `).join('');
}

function renderHistoryResults(items) {
    return items.map((historyItem) => `
        <a href="${escapeHtml(historyItem.url)}" target="_blank" rel="noreferrer" class="block px-5 py-4 hover:bg-zinc-50">
            <div class="truncate text-sm font-medium text-zinc-950">${escapeHtml(historyItem.title || historyItem.url || 'Untitled history item')}</div>
            <div class="mt-1 truncate text-xs text-zinc-500">${escapeHtml(historyItem.url || '')}</div>
            <div class="mt-2 text-xs text-zinc-500">
                ${escapeHtml(historyItem.device?.name || 'Unknown device')} - ${escapeHtml(formatDate(historyItem.visited_at))}
            </div>
        </a>
    `).join('');
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
