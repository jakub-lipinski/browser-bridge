import './modules/initSafariAdapter';
import '../../chrome-extension/src/styles.css';
import { getBrowserAdapter, type BrowserCapabilityAudit } from '../../chrome-extension/src/modules/browserAdapter';
import { deleteSyncedHistory, searchBookmarks, searchHistory } from '../../chrome-extension/src/modules/apiClient';
import { getConfig, saveConfig } from '../../chrome-extension/src/modules/storage';
import type {
  DeviceResource,
  ExtensionConfig,
  HistoryItemResource,
  HistorySyncRange,
  NormalizedBookmarkResource,
  TabCommandResource,
  TabSnapshotItem,
  TabSnapshotResource,
} from '../../chrome-extension/src/modules/types';

type SafariLoadErrorKey = 'devices' | 'incomingCommands' | 'bookmarks' | 'historyItems' | 'tabSnapshots';
type SafariLoadErrors = Partial<Record<SafariLoadErrorKey, string>>;

type SafariRefreshSummary = {
  refreshedAt: string;
  bookmarksUploaded?: number;
  bookmarksUploadUnavailable?: string;
  historyUploaded?: number;
  historyUploadMode?: 'native' | 'activity' | 'unavailable';
  historyUploadUnavailable?: string;
  tabsUploaded?: number;
  devices?: number;
  incomingCommands?: number;
  remoteBookmarks?: number;
  remoteHistoryItems?: number;
  remoteTabSnapshots?: number;
  errors: SafariLoadErrors;
  unsupportedMessages: string[];
};

type PopupStatus = {
  config: ExtensionConfig;
  configured: boolean;
  devices: DeviceResource[];
  incomingCommands: TabCommandResource[];
  currentTab: TabSnapshotItem | null;
  bookmarks: NormalizedBookmarkResource[];
  historyItems: HistoryItemResource[];
  tabSnapshots: TabSnapshotResource[];
  loadErrors: SafariLoadErrors;
  capabilityAudit: BrowserCapabilityAudit;
  refreshSummary?: SafariRefreshSummary;
};

type RuntimeResponse<T> = {
  ok: boolean;
  payload?: T;
  error?: string;
};

type SearchState<T> = {
  all: T[];
  results: T[];
  expanded: boolean;
  query: string;
  loading: boolean;
  error: string | null;
  requestId: number;
  timer?: number;
};

const VISIBLE_RESULT_LIMIT = 5;
const REMOTE_SEARCH_MIN_LENGTH = 2;
const SEARCH_DEBOUNCE_MS = 250;

const elements = {
  connectionStatus: document.querySelector<HTMLSpanElement>('#connection-status'),
  deviceSummary: document.querySelector<HTMLParagraphElement>('#device-summary'),
  lastSync: document.querySelector<HTMLSpanElement>('#last-sync'),
  errorMessage: document.querySelector<HTMLParagraphElement>('#error-message'),
  syncNow: document.querySelector<HTMLButtonElement>('#sync-now'),
  sendTarget: document.querySelector<HTMLSelectElement>('#send-target'),
  sendCurrentTab: document.querySelector<HTMLButtonElement>('#send-current-tab'),
  sendStatus: document.querySelector<HTMLParagraphElement>('#send-status'),
  sendToast: document.querySelector<HTMLParagraphElement>('#send-toast'),
  activityDot: document.querySelector<HTMLSpanElement>('#activity-dot'),
  currentTabTitle: document.querySelector<HTMLParagraphElement>('#current-tab-title'),
  currentTabUrl: document.querySelector<HTMLParagraphElement>('#current-tab-url'),
  devicesList: document.querySelector<HTMLDivElement>('#devices-list'),
  bookmarksList: document.querySelector<HTMLDivElement>('#bookmarks-list'),
  bookmarksQuery: document.querySelector<HTMLInputElement>('#bookmarks-query'),
  bookmarksCount: document.querySelector<HTMLParagraphElement>('#bookmarks-count'),
  bookmarksMore: document.querySelector<HTMLButtonElement>('#bookmarks-more'),
  bookmarksLess: document.querySelector<HTMLButtonElement>('#bookmarks-less'),
  historyList: document.querySelector<HTMLDivElement>('#history-list'),
  historyQuery: document.querySelector<HTMLInputElement>('#history-query'),
  historyCount: document.querySelector<HTMLParagraphElement>('#history-count'),
  historyMore: document.querySelector<HTMLButtonElement>('#history-more'),
  historyLess: document.querySelector<HTMLButtonElement>('#history-less'),
  deleteHistory: document.querySelector<HTMLButtonElement>('#delete-history'),
  commandsList: document.querySelector<HTMLDivElement>('#commands-list'),
  incomingCount: document.querySelector<HTMLSpanElement>('#incoming-count'),
  bookmarksStat: document.querySelector<HTMLElement>('#bookmarks-stat'),
  historyStat: document.querySelector<HTMLElement>('#history-stat'),
  tabsStat: document.querySelector<HTMLElement>('#tabs-stat'),
  safariBookmarksUploadStatus: document.querySelector<HTMLParagraphElement>('#safari-bookmarks-upload-status'),
  safariHistoryUploadStatus: document.querySelector<HTMLParagraphElement>('#safari-history-upload-status'),
  toggleBookmarks: document.querySelector<HTMLInputElement>('#toggle-bookmarks'),
  toggleHistory: document.querySelector<HTMLInputElement>('#toggle-history'),
  historySettingsContainer: document.querySelector<HTMLDivElement>('#history-settings-container'),
  historySyncRange: document.querySelector<HTMLSelectElement>('#history-sync-range'),
  historyRangeWarning: document.querySelector<HTMLDivElement>('#history-range-warning'),
  historyConfirmationModal: document.querySelector<HTMLDivElement>('#history-confirmation-modal'),
  cancelHistoryEnable: document.querySelector<HTMLButtonElement>('#cancel-history-enable'),
  confirmHistoryEnable: document.querySelector<HTMLButtonElement>('#confirm-history-enable'),
  capabilityCurrentTab: document.querySelector<HTMLParagraphElement>('#capability-current-tab'),
  capabilityAllTabs: document.querySelector<HTMLParagraphElement>('#capability-all-tabs'),
  capabilityBookmarks: document.querySelector<HTMLParagraphElement>('#capability-bookmarks'),
  capabilityHistory: document.querySelector<HTMLParagraphElement>('#capability-history'),
  capabilityBackground: document.querySelector<HTMLParagraphElement>('#capability-background'),
  syncSourceStatus: document.querySelector<HTMLParagraphElement>('#sync-source-status'),
  capabilityDebugList: document.querySelector<HTMLDivElement>('#capability-debug-list'),
  refreshSummary: document.querySelector<HTMLParagraphElement>('#refresh-summary'),
  openOptions: document.querySelector<HTMLButtonElement>('#open-options'),
};

const bookmarkState: SearchState<NormalizedBookmarkResource> = {
  all: [],
  results: [],
  expanded: false,
  query: '',
  loading: false,
  error: null,
  requestId: 0,
};

const historyState: SearchState<HistoryItemResource> = {
  all: [],
  results: [],
  expanded: false,
  query: '',
  loading: false,
  error: null,
  requestId: 0,
};

let sentFeedbackTimer: number | undefined;

function requireElement<T>(element: T | null): T {
  if (!element) {
    throw new Error('BrowserBridge Safari popup did not initialize.');
  }

  return element;
}

async function sendMessage<T>(message: Record<string, unknown>): Promise<T> {
  const response = await chrome.runtime.sendMessage(message) as RuntimeResponse<T>;

  if (!response.ok) {
    throw new Error(response.error || 'BrowserBridge background request failed.');
  }

  return response.payload as T;
}

function setError(message: string | null): void {
  requireElement(elements.errorMessage).textContent = message || '';
}

function setSendStatus(message: string | null): void {
  requireElement(elements.sendStatus).textContent = message || '';
}

function showSentFeedback(message: string): void {
  window.clearTimeout(sentFeedbackTimer);

  const toast = requireElement(elements.sendToast);
  const activityDot = requireElement(elements.activityDot);
  toast.textContent = message;
  toast.hidden = false;
  activityDot.hidden = false;
  activityDot.classList.add('active');
  setSendStatus(message);

  sentFeedbackTimer = window.setTimeout(() => {
    toast.hidden = true;
    activityDot.hidden = true;
    activityDot.classList.remove('active');
  }, 3200);
}

function loadErrorMessage(status: PopupStatus): string | null {
  const loadErrors = Object.values(status.loadErrors || {});

  if (loadErrors.length > 0) {
    return Array.from(new Set(loadErrors)).join(' | ');
  }

  if (status.config.lastError) {
    return status.config.lastError;
  }

  return null;
}

function crossBrowserTargets(status: PopupStatus): DeviceResource[] {
  return status.devices.filter((device) => device.uuid !== status.config.deviceUuid && device.browser === 'chrome');
}

function latestTabSnapshotCount(tabSnapshots: TabSnapshotResource[] = []): number {
  const latestByDevice = new Map<number, TabSnapshotResource>();

  tabSnapshots.forEach((snapshot) => {
    if (!latestByDevice.has(snapshot.device_id)) {
      latestByDevice.set(snapshot.device_id, snapshot);
    }
  });

  return [...latestByDevice.values()].reduce((total, snapshot) => total + snapshot.tab_count, 0);
}

function activateTab(tab: string): void {
  document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((button) => {
    button.classList.toggle('active', button.dataset.tab === tab);
  });

  document.querySelectorAll<HTMLElement>('.tab-panel').forEach((panel) => {
    const isActive = panel.id === `${tab}-panel`;
    panel.hidden = !isActive;
    panel.classList.toggle('active', isActive);
  });

  if (tab === 'send') {
    document.querySelector('#send-panel')?.scrollIntoView({ block: 'nearest' });
  }

  if (tab === 'incoming') {
    const sendPanel = document.querySelector<HTMLElement>('#send-panel');
    if (sendPanel) {
      sendPanel.hidden = false;
      sendPanel.classList.add('active');
    }
    document.querySelector('#commands-list')?.scrollIntoView({ block: 'nearest' });
  }
}

function renderSendTargets(status: PopupStatus): void {
  const select = requireElement(elements.sendTarget);
  const sendButton = requireElement(elements.sendCurrentTab);
  const targets = crossBrowserTargets(status);
  select.textContent = '';

  if (targets.length === 0 || status.loadErrors.devices) {
    const option = document.createElement('option');
    option.textContent = status.loadErrors.devices ? 'Could not load devices' : 'No Chrome device';
    option.value = '';
    select.append(option);
    sendButton.disabled = true;

    return;
  }

  targets.forEach((device) => {
    const option = document.createElement('option');
    option.value = device.uuid;
    option.textContent = device.name;
    select.append(option);
  });

  sendButton.disabled = !status.currentTab;
}

function renderDevices(status: PopupStatus): void {
  const devicesList = requireElement(elements.devicesList);
  devicesList.textContent = '';

  if (status.loadErrors.devices) {
    devicesList.innerHTML = '<p class="muted">Could not load devices</p>';

    return;
  }

  const targets = crossBrowserTargets(status);

  if (targets.length === 0) {
    devicesList.innerHTML = '<p class="muted">No Chrome device connected yet.</p>';

    return;
  }

  targets.slice(0, 3).forEach((device) => {
    const item = document.createElement('div');
    item.className = 'flex flex-col gap-0.5 mb-2 last:mb-0';

    const title = document.createElement('div');
    title.className = 'text-sm font-semibold text-[var(--color-text)] truncate';
    title.textContent = `${device.name} (${device.browser} on ${device.platform})`;

    const meta = document.createElement('p');
    meta.className = 'text-[11px] text-[var(--color-muted)] truncate m-0';
    meta.textContent = device.last_seen_at ? `Last seen ${new Date(device.last_seen_at).toLocaleString()}` : 'Never seen';

    item.append(title, meta);
    devicesList.append(item);
  });
}

function createFavicon(urlStr: string, sizeClass = 'w-6 h-6'): HTMLElement {
  const wrapper = document.createElement('div');
  wrapper.className = `${sizeClass} shrink-0 relative flex items-center justify-center`;

  try {
    const domain = new URL(urlStr).hostname;
    if (domain) {
      const firstLetter = domain.replace(/^www\./, '')[0]?.toUpperCase() || '?';
      
      const fallback = document.createElement('div');
      fallback.className = `absolute inset-0 rounded-[var(--radius-sm)] bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-[10px] font-bold text-[var(--color-muted)] uppercase`;
      fallback.textContent = firstLetter;
      
      const img = document.createElement('img');
      img.src = `https://www.google.com/s2/favicons?domain=${domain}&sz=32`;
      img.className = `absolute inset-0 w-full h-full object-contain rounded-[var(--radius-sm)] bg-[var(--color-surface)] shadow-sm border border-[var(--color-border)] p-0.5 z-10`;
      img.loading = 'lazy';
      img.onerror = () => img.remove();
      
      wrapper.append(fallback, img);
      return wrapper;
    }
  } catch {
    // ignore
  }
  
  const fallback = document.createElement('div');
  fallback.className = `absolute inset-0 rounded-[var(--radius-sm)] bg-[var(--color-surface-muted)] border border-[var(--color-border)] flex items-center justify-center text-[10px] font-bold text-[var(--color-muted)] uppercase`;
  fallback.textContent = '?';
  wrapper.append(fallback);
  return wrapper;
}

function renderCommands(status: PopupStatus): void {
  const commandsList = requireElement(elements.commandsList);
  commandsList.textContent = '';

  if (status.loadErrors.incomingCommands) {
    commandsList.innerHTML = '<p class="muted">Could not load incoming tabs</p>';
    requireElement(elements.incomingCount).textContent = 'failed';
    requireElement(elements.incomingCount).classList.remove('accent');

    return;
  }

  const incomingCount = requireElement(elements.incomingCount);
  incomingCount.textContent = `${status.incomingCommands.length} pending`;
  incomingCount.classList.toggle('accent', status.incomingCommands.length > 0);

  if (status.incomingCommands.length === 0) {
    commandsList.innerHTML = '<p class="muted">No incoming tab commands.</p>';

    return;
  }

  status.incomingCommands.slice(0, 5).forEach((command) => {
    const item = document.createElement('div');
    item.className = 'flex items-center gap-3 p-2 bg-[var(--color-surface)] border border-[var(--color-border)] rounded-[var(--radius-sm)] min-w-0';

    const title = document.createElement('div');
    title.className = 'text-[13px] font-semibold text-[var(--color-text)] truncate';
    title.textContent = command.title || command.url || 'Untitled tab';

    const url = document.createElement('p');
    url.className = 'text-[11px] text-[var(--color-muted)] truncate m-0';
    url.textContent = command.url || '';

    const actions = document.createElement('div');
    actions.className = 'flex items-center gap-2 mt-1';

    const openButton = document.createElement('button');
    openButton.type = 'button';
    openButton.className = 'flex-1 text-[11px] font-bold px-2 py-1 bg-[var(--color-primary)] text-white rounded border border-[var(--color-primary-strong)] hover:bg-[var(--color-primary-strong)] transition-colors';
    openButton.textContent = 'Open';
    openButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({ type: 'browserbridge.openCommand', command })
        .then(render)
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to open command.'));
    });

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'flex-1 text-[11px] font-semibold px-2 py-1 bg-[var(--color-surface-muted)] text-[var(--color-text)] rounded border border-[var(--color-border-strong)] hover:bg-[var(--color-border)] transition-colors';
    dismissButton.textContent = 'Dismiss';
    dismissButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({ type: 'browserbridge.dismissCommand', commandId: command.id })
        .then(render)
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to dismiss command.'));
    });

    const content = document.createElement('div');
    content.className = 'flex flex-col gap-1 min-w-0';
    content.append(title, url, actions);

    item.append(createFavicon(command.url || '', 'w-8 h-8'), content);
    commandsList.append(item);
  });
}

function groupBookmarksByDevice(bookmarks: NormalizedBookmarkResource[]): Map<string, NormalizedBookmarkResource[]> {
  return bookmarks.reduce((groups, bookmark) => {
    const deviceName = bookmark.device?.name || 'Unknown device';
    const group = groups.get(deviceName) || [];
    group.push(bookmark);
    groups.set(deviceName, group);

    return groups;
  }, new Map<string, NormalizedBookmarkResource[]>());
}

function groupHistoryItemsByDevice(historyItems: HistoryItemResource[]): Map<string, HistoryItemResource[]> {
  return historyItems.reduce((groups, historyItem) => {
    const deviceName = historyItem.device?.name || 'Unknown device';
    const group = groups.get(deviceName) || [];
    group.push(historyItem);
    groups.set(deviceName, group);

    return groups;
  }, new Map<string, HistoryItemResource[]>());
}

function renderBookmarks(loadError?: string): void {
  const list = requireElement(elements.bookmarksList);
  const count = requireElement(elements.bookmarksCount);
  const more = requireElement(elements.bookmarksMore);
  const less = requireElement(elements.bookmarksLess);
  list.textContent = '';

  if (loadError) {
    list.innerHTML = '<p class="muted">Could not load bookmarks</p>';
    count.textContent = loadError;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  if (bookmarkState.loading) {
    list.innerHTML = '<p class="muted">Loading bookmarks...</p>';
    count.textContent = 'Searching bookmarks...';
    more.hidden = true;
    less.hidden = true;

    return;
  }

  if (bookmarkState.error) {
    list.innerHTML = '<p class="muted">Could not load bookmarks.</p>';
    count.textContent = bookmarkState.error;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  count.textContent = `${bookmarkState.results.length} ${bookmarkState.results.length === 1 ? 'bookmark' : 'bookmarks'}`;

  if (bookmarkState.results.length === 0) {
    list.innerHTML = `<p class="muted">${bookmarkState.query ? 'No bookmark results.' : 'No BrowserBridge bookmarks available yet.'}</p>`;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  const visible = bookmarkState.expanded ? bookmarkState.results : bookmarkState.results.slice(0, VISIBLE_RESULT_LIMIT);

  groupBookmarksByDevice(visible).forEach((items, deviceName) => {
    const groupTitle = document.createElement('div');
    groupTitle.className = 'text-[10px] font-bold text-[var(--color-muted)] uppercase tracking-wider mb-1 mt-2 first:mt-0';
    groupTitle.textContent = deviceName;
    list.append(groupTitle);

    items.forEach((bookmark) => {
      const item = document.createElement('div');
      item.tabIndex = 0;

      const title = document.createElement('div');
      title.className = 'text-[13px] font-semibold text-[var(--color-text)] truncate';
      title.textContent = bookmark.title || bookmark.url || 'Untitled bookmark';

      const url = document.createElement('p');
      url.className = 'text-[11px] text-[var(--color-muted)] truncate m-0';
      url.textContent = bookmark.url || '';

      const path = document.createElement('p');
      path.className = 'text-[10px] text-[var(--color-muted)] opacity-70 truncate m-0';
      path.textContent = bookmark.path.length > 0 ? bookmark.path.join(' / ') : 'No folder path';

      const open = (): void => {
        if (bookmark.url) {
          void getBrowserAdapter().openTab(bookmark.url).catch((error: unknown) => {
            setError(error instanceof Error ? error.message : 'Unable to open bookmark.');
          });
        }
      };

      item.addEventListener('click', open);
      item.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          open();
        }
      });

      const content = document.createElement('div');
      content.className = 'flex flex-col gap-0.5 min-w-0';
      content.append(title, url, path);

      item.className = 'flex items-center gap-2.5 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors cursor-pointer outline-none focus:ring-2 focus:ring-[var(--color-primary)]';
      item.append(createFavicon(bookmark.url || ''), content);
      list.append(item);
    });
  });

  more.hidden = bookmarkState.expanded || bookmarkState.results.length <= VISIBLE_RESULT_LIMIT;
  less.hidden = !bookmarkState.expanded || bookmarkState.results.length <= VISIBLE_RESULT_LIMIT;
}

function renderHistoryItems(loadError?: string): void {
  const list = requireElement(elements.historyList);
  const count = requireElement(elements.historyCount);
  const more = requireElement(elements.historyMore);
  const less = requireElement(elements.historyLess);
  list.textContent = '';

  if (loadError) {
    list.innerHTML = '<p class="muted">Could not load history</p>';
    count.textContent = loadError;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  if (historyState.loading) {
    list.innerHTML = '<p class="muted">Loading history...</p>';
    count.textContent = 'Searching history...';
    more.hidden = true;
    less.hidden = true;

    return;
  }

  if (historyState.error) {
    list.innerHTML = '<p class="muted">Could not load history.</p>';
    count.textContent = historyState.error;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  count.textContent = `${historyState.results.length} ${historyState.results.length === 1 ? 'history item' : 'history items'}`;

  if (historyState.results.length === 0) {
    list.innerHTML = `<p class="muted">${historyState.query ? 'No history results.' : 'No BrowserBridge history available yet.'}</p>`;
    more.hidden = true;
    less.hidden = true;

    return;
  }

  const visible = historyState.expanded ? historyState.results : historyState.results.slice(0, VISIBLE_RESULT_LIMIT);

  groupHistoryItemsByDevice(visible).forEach((items, deviceName) => {
    const groupTitle = document.createElement('div');
    groupTitle.className = 'text-[10px] font-bold text-[var(--color-muted)] uppercase tracking-wider mb-1 mt-2 first:mt-0';
    groupTitle.textContent = deviceName;
    list.append(groupTitle);

    items.forEach((historyItem) => {
      const item = document.createElement('div');
      item.tabIndex = 0;

      const title = document.createElement('div');
      title.className = 'text-[13px] font-semibold text-[var(--color-text)] truncate';
      title.textContent = historyItem.title || historyItem.url;

      const url = document.createElement('p');
      url.className = 'text-[11px] text-[var(--color-muted)] truncate m-0';
      url.textContent = historyItem.url;

      const visitedAt = document.createElement('p');
      visitedAt.className = 'text-[10px] text-[var(--color-muted)] opacity-70 truncate m-0';
      visitedAt.textContent = historyItem.visited_at ? new Date(historyItem.visited_at).toLocaleString() : '';

      const open = (): void => {
        if (historyItem.url) {
          void getBrowserAdapter().openTab(historyItem.url).catch((error: unknown) => {
            setError(error instanceof Error ? error.message : 'Unable to open history item.');
          });
        }
      };

      item.addEventListener('click', open);
      item.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          open();
        }
      });

      const content = document.createElement('div');
      content.className = 'flex flex-col gap-0.5 min-w-0';
      content.append(title, url, visitedAt);

      item.className = 'flex items-center gap-2.5 p-2 rounded-[var(--radius-sm)] hover:bg-[var(--color-surface-muted)] border border-transparent hover:border-[var(--color-border)] transition-colors cursor-pointer outline-none focus:ring-2 focus:ring-[var(--color-primary)]';
      item.append(createFavicon(historyItem.url || ''), content);
      list.append(item);
    });
  });

  more.hidden = historyState.expanded || historyState.results.length <= VISIBLE_RESULT_LIMIT;
  less.hidden = !historyState.expanded || historyState.results.length <= VISIBLE_RESULT_LIMIT;
}

function matchesBookmark(bookmark: NormalizedBookmarkResource, query: string): boolean {
  const search = `${bookmark.title || ''} ${bookmark.url || ''} ${bookmark.path.join(' ')}`.toLowerCase();

  return search.includes(query.toLowerCase());
}

function matchesHistory(historyItem: HistoryItemResource, query: string): boolean {
  const search = `${historyItem.title || ''} ${historyItem.url || ''}`.toLowerCase();

  return search.includes(query.toLowerCase());
}

function scheduleBookmarkSearch(): void {
  window.clearTimeout(bookmarkState.timer);
  bookmarkState.query = requireElement(elements.bookmarksQuery).value.trim();
  bookmarkState.expanded = false;
  bookmarkState.error = null;
  bookmarkState.results = bookmarkState.query
    ? bookmarkState.all.filter((bookmark) => matchesBookmark(bookmark, bookmarkState.query))
    : bookmarkState.all;
  renderBookmarks();

  if (bookmarkState.query.length < REMOTE_SEARCH_MIN_LENGTH) {
    return;
  }

  bookmarkState.timer = window.setTimeout(() => {
    const requestId = ++bookmarkState.requestId;
    bookmarkState.loading = true;
    renderBookmarks();

    void getConfig()
      .then((config) => searchBookmarks(config, bookmarkState.query))
      .then((bookmarks) => {
        if (requestId !== bookmarkState.requestId) {
          return;
        }

        bookmarkState.results = bookmarks;
        bookmarkState.error = null;
      })
      .catch((error: unknown) => {
        bookmarkState.error = error instanceof Error ? error.message : 'Could not load bookmarks.';
      })
      .finally(() => {
        if (requestId === bookmarkState.requestId) {
          bookmarkState.loading = false;
          renderBookmarks();
        }
      });
  }, SEARCH_DEBOUNCE_MS);
}

function scheduleHistorySearch(): void {
  window.clearTimeout(historyState.timer);
  historyState.query = requireElement(elements.historyQuery).value.trim();
  historyState.expanded = false;
  historyState.error = null;
  historyState.results = historyState.query
    ? historyState.all.filter((historyItem) => matchesHistory(historyItem, historyState.query))
    : historyState.all;
  renderHistoryItems();

  if (historyState.query.length < REMOTE_SEARCH_MIN_LENGTH) {
    return;
  }

  historyState.timer = window.setTimeout(() => {
    const requestId = ++historyState.requestId;
    historyState.loading = true;
    renderHistoryItems();

    void getConfig()
      .then((config) => searchHistory(config, historyState.query))
      .then((historyItems) => {
        if (requestId !== historyState.requestId) {
          return;
        }

        historyState.results = historyItems;
        historyState.error = null;
      })
      .catch((error: unknown) => {
        historyState.error = error instanceof Error ? error.message : 'Could not load history.';
      })
      .finally(() => {
        if (requestId === historyState.requestId) {
          historyState.loading = false;
          renderHistoryItems();
        }
      });
  }, SEARCH_DEBOUNCE_MS);
}

function formatRefreshPart(label: string, value: number | undefined, error?: string): string {
  if (error) {
    return `${label}: failed`;
  }

  return `${label}: ${value ?? 0}`;
}

function formatUploadPart(label: string, value: number | undefined, unavailable?: string): string {
  if (unavailable) {
    return `${label}: not available`;
  }

  if (value === undefined) {
    return `${label}: not enabled`;
  }

  return `${label}: ${value}`;
}

function renderRefreshSummary(summary?: SafariRefreshSummary): void {
  const summaryElement = requireElement(elements.refreshSummary);

  if (!summary) {
    return;
  }

  const hasErrors = Object.keys(summary.errors).length > 0;
  const parts = [
    formatUploadPart('Bookmarks uploaded', summary.bookmarksUploaded, summary.bookmarksUploadUnavailable),
    formatUploadPart(summary.historyUploadMode === 'activity' ? 'Activity uploaded' : 'History uploaded', summary.historyUploaded, summary.historyUploadUnavailable),
    formatUploadPart('Tabs uploaded', summary.tabsUploaded),
    formatRefreshPart('Remote bookmarks', summary.remoteBookmarks, summary.errors.bookmarks),
    formatRefreshPart('Remote history', summary.remoteHistoryItems, summary.errors.historyItems),
    formatRefreshPart('Incoming tabs', summary.incomingCommands, summary.errors.incomingCommands),
  ];

  summaryElement.textContent = `${hasErrors ? 'Safari sync completed with errors.' : 'Safari sync complete.'} ${parts.join(' | ')}`;
  requireElement(elements.safariBookmarksUploadStatus).textContent = summary.bookmarksUploadUnavailable
    ? summary.bookmarksUploadUnavailable
    : `Safari bookmarks synced: ${summary.bookmarksUploaded ?? 'not enabled'}`;
  requireElement(elements.safariHistoryUploadStatus).textContent = summary.historyUploadUnavailable
    ? summary.historyUploadUnavailable
    : `${summary.historyUploadMode === 'activity' ? 'BrowserBridge Activity saved' : 'Safari history synced'}: ${summary.historyUploaded ?? 'not enabled'}`;
}

function availabilityLabel(available: boolean): string {
  return available ? 'Available' : 'Not available';
}

function syncButtonLabel(audit: BrowserCapabilityAudit): string {
  const nativeSourceCount = Number(audit.canReadNativeBookmarks) + Number(audit.canReadNativeHistory);

  if (nativeSourceCount === 2) {
    return 'Sync now';
  }

  if (nativeSourceCount > 0 || audit.historyMode === 'activity' || audit.canReadAllTabs || audit.canReadCurrentTab) {
    return 'Sync / Refresh';
  }

  return 'Refresh';
}

function renderCapabilityAudit(audit: BrowserCapabilityAudit): void {
  requireElement(elements.syncNow).textContent = syncButtonLabel(audit);
  requireElement(elements.capabilityCurrentTab).textContent = `Read current tab: ${availabilityLabel(audit.canReadCurrentTab)}`;
  requireElement(elements.capabilityAllTabs).textContent = `Read open tabs: ${availabilityLabel(audit.canReadAllTabs)}`;
  requireElement(elements.capabilityBookmarks).textContent = `Native Safari bookmark reading: ${availabilityLabel(audit.canReadNativeBookmarks)}`;
  requireElement(elements.capabilityHistory).textContent = `Native Safari history reading: ${availabilityLabel(audit.canReadNativeHistory)}`;
  requireElement(elements.capabilityBackground).textContent = `Background polling: ${availabilityLabel(audit.canUseBackgroundPolling)}`;
  requireElement(elements.safariBookmarksUploadStatus).textContent = audit.canReadNativeBookmarks
    ? 'Native Safari bookmark reading is available.'
    : 'Native Safari bookmark reading is not available in this Safari version.';
  requireElement(elements.safariHistoryUploadStatus).textContent = audit.canReadNativeHistory
    ? 'Native Safari history reading is available.'
    : 'Full native Safari history upload is not available in this Safari version. BrowserBridge can still save Safari pages you send/open through the extension.';
  requireElement(elements.syncSourceStatus).textContent = audit.historyMode === 'activity'
    ? 'BrowserBridge Activity is not the same as full Safari history. It only saves Safari pages you send/open through the extension, plus the active tab when you sync.'
    : 'Safari checks source capabilities first, uploads available data, then refreshes BrowserBridge data from your server.';

  const debugList = requireElement(elements.capabilityDebugList);
  debugList.textContent = '';

  audit.probes.forEach((probe) => {
    const item = document.createElement('div');
    item.className = 'flex flex-col gap-0.5 mb-2 last:mb-0';

    const title = document.createElement('div');
    title.className = 'text-[13px] font-semibold text-[var(--color-text)] truncate';
    title.textContent = `${probe.name}: ${probe.success ? 'success' : 'failed'}`;

    const meta = document.createElement('p');
    meta.className = 'text-[11px] text-[var(--color-muted)] truncate m-0';
    meta.textContent = probe.error ? `${probe.api} - ${probe.error}` : probe.api;

    item.append(title, meta);
    debugList.append(item);
  });
}

function render(status: PopupStatus): void {
  const connectionStatus = requireElement(elements.connectionStatus);
  connectionStatus.textContent = status.configured ? 'Connected' : 'Disconnected';
  connectionStatus.className = `status ${status.configured ? 'ok' : 'bad'}`;

  requireElement(elements.deviceSummary).textContent = status.config.deviceName || 'No Safari device configured';
  requireElement(elements.lastSync).textContent = status.config.lastSyncAt
    ? new Date(status.config.lastSyncAt).toLocaleString()
    : 'Never';

  requireElement(elements.currentTabTitle).textContent = status.currentTab?.title || 'No syncable current tab.';
  requireElement(elements.currentTabUrl).textContent = status.currentTab?.url || '';
  requireElement(elements.toggleBookmarks).checked = status.config.sync.bookmarks;
  requireElement(elements.toggleHistory).checked = status.config.sync.history;
  requireElement(elements.historySettingsContainer).hidden = !status.config.sync.history;
  requireElement(elements.historySyncRange).value = status.config.historySyncRange || '24h';
  requireElement(elements.historyRangeWarning).hidden = status.config.historySyncRange !== 'all';
  setError(loadErrorMessage(status));
  setSendStatus(null);

  bookmarkState.all = status.bookmarks || [];
  bookmarkState.results = bookmarkState.query
    ? bookmarkState.all.filter((bookmark) => matchesBookmark(bookmark, bookmarkState.query))
    : bookmarkState.all;
  historyState.all = status.historyItems || [];
  historyState.results = historyState.query
    ? historyState.all.filter((historyItem) => matchesHistory(historyItem, historyState.query))
    : historyState.all;

  requireElement(elements.bookmarksStat).textContent = status.loadErrors.bookmarks ? '!' : String(bookmarkState.all.length);
  requireElement(elements.historyStat).textContent = status.loadErrors.historyItems ? '!' : String(historyState.all.length);
  requireElement(elements.tabsStat).textContent = status.loadErrors.tabSnapshots ? '!' : String(latestTabSnapshotCount(status.tabSnapshots));

  renderSendTargets(status);
  renderDevices(status);
  renderCommands(status);
  renderBookmarks(status.loadErrors.bookmarks);
  renderHistoryItems(status.loadErrors.historyItems);
  renderCapabilityAudit(status.capabilityAudit);
}

async function refresh(): Promise<void> {
  const status = await sendMessage<PopupStatus>({ type: 'browserbridge.getStatus' });
  render(status);
}

requireElement(elements.syncNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.refreshNow' })
    .then((status) => {
      render(status);
      renderRefreshSummary(status.refreshSummary);
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to refresh BrowserBridge data.'));
});

async function updateToggles(): Promise<void> {
  const config = await getConfig();
  const historyEnabled = requireElement(elements.toggleHistory).checked;

  await saveConfig({
    ...config,
    syncHistory: historyEnabled,
    sync: {
      ...config.sync,
      bookmarks: requireElement(elements.toggleBookmarks).checked,
      history: historyEnabled,
    },
  });

  await refresh();
}

function showHistoryConfirmationModal(): void {
  requireElement(elements.historyConfirmationModal).hidden = false;
}

function hideHistoryConfirmationModal(): void {
  requireElement(elements.historyConfirmationModal).hidden = true;
}

async function handleHistoryToggle(): Promise<void> {
  const config = await getConfig();
  const historyToggle = requireElement(elements.toggleHistory);

  if (historyToggle.checked && !config.historyConsentConfirmedAt) {
    historyToggle.checked = false;
    showHistoryConfirmationModal();

    return;
  }

  await updateToggles();
}

requireElement(elements.sendCurrentTab).addEventListener('click', () => {
  const targetDeviceUuid = requireElement(elements.sendTarget).value;
  const targetName = requireElement(elements.sendTarget).selectedOptions[0]?.textContent || 'Chrome';

  if (!targetDeviceUuid) {
    setSendStatus('No Chrome device is available.');

    return;
  }

  void sendMessage<PopupStatus>({ type: 'browserbridge.sendCurrentTab', targetDeviceUuid })
    .then((nextStatus) => {
      render(nextStatus);
      showSentFeedback(`Sent to ${targetName}.`);
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to send tab.'));
});

requireElement(elements.openOptions).addEventListener('click', () => {
  void chrome.runtime.openOptionsPage();
});

requireElement(elements.toggleBookmarks).addEventListener('change', () => {
  void updateToggles().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to update bookmark sync.');
  });
});

requireElement(elements.toggleHistory).addEventListener('change', () => {
  void handleHistoryToggle().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to update Safari history or Activity sync.');
  });
});

requireElement(elements.cancelHistoryEnable).addEventListener('click', () => {
  hideHistoryConfirmationModal();
  requireElement(elements.toggleHistory).checked = false;
});

requireElement(elements.confirmHistoryEnable).addEventListener('click', () => {
  void getConfig()
    .then(async (config) => {
      await saveConfig({
        ...config,
        syncHistory: true,
        sync: {
          ...config.sync,
          history: true,
        },
        historyConsentConfirmedAt: new Date().toISOString(),
      });
    })
    .then(() => {
      hideHistoryConfirmationModal();
      requireElement(elements.toggleHistory).checked = true;

      return refresh();
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to enable Safari history or Activity sync.'));
});

requireElement(elements.historySyncRange).addEventListener('change', (event) => {
  const value = (event.target as HTMLSelectElement).value as HistorySyncRange;
  requireElement(elements.historyRangeWarning).hidden = value !== 'all';

  void getConfig()
    .then((config) => saveConfig({ ...config, historySyncRange: value }))
    .catch((error: unknown) => {
      setError(error instanceof Error ? error.message : 'Unable to update Safari history range.');
    });
});

requireElement(elements.historyQuery).addEventListener('input', scheduleHistorySearch);
requireElement(elements.bookmarksQuery).addEventListener('input', scheduleBookmarkSearch);

requireElement(elements.bookmarksMore).addEventListener('click', () => {
  bookmarkState.expanded = true;
  renderBookmarks();
});

requireElement(elements.bookmarksLess).addEventListener('click', () => {
  bookmarkState.expanded = false;
  renderBookmarks();
});

requireElement(elements.historyMore).addEventListener('click', () => {
  historyState.expanded = true;
  renderHistoryItems();
});

requireElement(elements.historyLess).addEventListener('click', () => {
  historyState.expanded = false;
  renderHistoryItems();
});

requireElement(elements.deleteHistory).addEventListener('click', () => {
  if (!confirm('Delete all synced BrowserBridge History from the server?')) {
    return;
  }

  void getConfig()
    .then(deleteSyncedHistory)
    .then(() => {
      historyState.all = [];
      historyState.results = [];
      renderHistoryItems();
      setError('Deleted synced BrowserBridge History.');
    })
    .catch((error: unknown) => {
      setError(error instanceof Error ? error.message : 'Unable to delete BrowserBridge History.');
    });
});

document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((button) => {
  button.addEventListener('click', () => activateTab(button.dataset.tab || 'send'));
});

activateTab('send');

void refresh().catch((error: unknown) => {
  setError(error instanceof Error ? error.message : 'Unable to load BrowserBridge Safari status.');
});
