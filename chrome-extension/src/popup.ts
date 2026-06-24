import './modules/initChromiumAdapter';
import './styles.css';
import { getBrowserAdapter } from './modules/browserAdapter';
import { getConfig, saveConfig } from './modules/storage';
import { deleteSyncedHistory, searchBookmarks, searchHistory } from './modules/apiClient';
import type {
  DeviceResource,
  ExtensionConfig,
  HistoryItemResource,
  HistorySyncRange,
  NormalizedBookmarkResource,
  SyncSummary,
  TabCommandResource,
  TabSnapshotItem,
  TabSnapshotResource,
} from './modules/types';

type PopupStatus = {
  config: ExtensionConfig;
  configured: boolean;
  devices: DeviceResource[];
  incomingCommands: TabCommandResource[];
  currentTab: TabSnapshotItem | null;
  bookmarks?: NormalizedBookmarkResource[];
  historyItems?: HistoryItemResource[];
  tabSnapshots?: TabSnapshotResource[];
  summary?: SyncSummary;
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
  toggleBookmarks: document.querySelector<HTMLInputElement>('#toggle-bookmarks'),
  toggleTabs: document.querySelector<HTMLInputElement>('#toggle-tabs'),
  toggleHistory: document.querySelector<HTMLInputElement>('#toggle-history'),
  currentTabTitle: document.querySelector<HTMLParagraphElement>('#current-tab-title'),
  currentTabUrl: document.querySelector<HTMLParagraphElement>('#current-tab-url'),
  devicesList: document.querySelector<HTMLDivElement>('#devices-list'),
  bookmarksList: document.querySelector<HTMLDivElement>('#bookmarks-list'),
  bookmarksQuery: document.querySelector<HTMLInputElement>('#bookmarks-query'),
  bookmarksCount: document.querySelector<HTMLParagraphElement>('#bookmarks-count'),
  bookmarksMore: document.querySelector<HTMLButtonElement>('#bookmarks-more'),
  bookmarksLess: document.querySelector<HTMLButtonElement>('#bookmarks-less'),
  syncBookmarksNow: document.querySelector<HTMLButtonElement>('#sync-bookmarks-now'),
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
  openOptions: document.querySelector<HTMLButtonElement>('#open-options'),
  historyConfirmationModal: document.querySelector<HTMLDivElement>('#history-confirmation-modal'),
  cancelHistoryEnable: document.querySelector<HTMLButtonElement>('#cancel-history-enable'),
  confirmHistoryEnable: document.querySelector<HTMLButtonElement>('#confirm-history-enable'),
  historySettingsContainer: document.querySelector<HTMLDivElement>('#history-settings-container'),
  historySyncRange: document.querySelector<HTMLSelectElement>('#history-sync-range'),
  historyRangeWarning: document.querySelector<HTMLDivElement>('#history-range-warning'),
  resyncHistory: document.querySelector<HTMLButtonElement>('#resync-history'),
};

let latestStatus: PopupStatus | null = null;
let sentFeedbackTimer: number | undefined;

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

function requireElement<T>(element: T | null): T {
  if (!element) {
    throw new Error('BrowserBridge popup did not initialize.');
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

function crossBrowserTargets(status: PopupStatus): DeviceResource[] {
  return status.devices.filter((device) => device.uuid !== status.config.deviceUuid && device.browser === 'safari');
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

  ['bookmarks', 'history', 'settings'].forEach((name) => {
    const panel = document.querySelector<HTMLElement>(`#${name}-panel`);

    if (panel) {
      panel.hidden = panel.id !== `${tab}-panel`;
    }
  });

  if (tab === 'send') {
    document.querySelector('#send-card')?.scrollIntoView({ block: 'nearest' });
  }

  if (tab === 'incoming') {
    document.querySelector('#incoming-panel')?.scrollIntoView({ block: 'nearest' });
  }
}

function renderSendTargets(status: PopupStatus): void {
  const select = requireElement(elements.sendTarget);
  const sendButton = requireElement(elements.sendCurrentTab);
  const targets = crossBrowserTargets(status);
  select.textContent = '';

  if (targets.length === 0) {
    const option = document.createElement('option');
    option.textContent = 'No Safari device';
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

  const targets = crossBrowserTargets(status);

  if (targets.length === 0) {
    devicesList.innerHTML = '<p class="muted">No Safari device connected yet.</p>';

    return;
  }

  targets.slice(0, 3).forEach((device) => {
    const item = document.createElement('div');
    item.className = 'item';

    const title = document.createElement('div');
    title.className = 'truncate';
    title.textContent = `${device.name} (${device.browser} on ${device.platform})`;

    const meta = document.createElement('p');
    meta.className = 'muted truncate';
    meta.textContent = device.last_seen_at ? `Last seen ${new Date(device.last_seen_at).toLocaleString()}` : 'Never seen';

    item.append(title, meta);
    devicesList.append(item);
  });
}

function renderCommands(status: PopupStatus): void {
  const commandsList = requireElement(elements.commandsList);
  commandsList.textContent = '';
  const incomingCount = requireElement(elements.incomingCount);
  incomingCount.textContent = `${status.incomingCommands.length} pending`;
  incomingCount.classList.toggle('accent', status.incomingCommands.length > 0);

  if (status.incomingCommands.length === 0) {
    commandsList.innerHTML = '<p class="muted">No incoming tab commands.</p>';

    return;
  }

  status.incomingCommands.slice(0, 5).forEach((command) => {
    const item = document.createElement('div');
    item.className = 'item';

    const title = document.createElement('div');
    title.className = 'truncate';
    title.textContent = command.title || command.url || 'Untitled tab';

    const url = document.createElement('p');
    url.className = 'muted truncate';
    url.textContent = command.url || '';

    const actions = document.createElement('div');
    actions.className = 'actions';

    const openButton = document.createElement('button');
    openButton.type = 'button';
    openButton.textContent = 'Open';
    openButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({ type: 'browserbridge.openCommand', command })
        .then(render)
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to open command.'));
    });

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'secondary';
    dismissButton.textContent = 'Dismiss';
    dismissButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({ type: 'browserbridge.dismissCommand', commandId: command.id })
        .then(render)
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to dismiss command.'));
    });

    actions.append(openButton, dismissButton);
    item.append(title, url, actions);
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

function renderBookmarks(): void {
  const list = requireElement(elements.bookmarksList);
  const count = requireElement(elements.bookmarksCount);
  const more = requireElement(elements.bookmarksMore);
  const less = requireElement(elements.bookmarksLess);
  list.textContent = '';

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
    groupTitle.className = 'group-title';
    groupTitle.textContent = deviceName;
    list.append(groupTitle);

    items.forEach((bookmark) => {
      const item = document.createElement('div');
      item.className = 'item clickable';
      item.tabIndex = 0;

      const title = document.createElement('div');
      title.className = 'truncate';
      title.textContent = bookmark.title || bookmark.url || 'Untitled bookmark';

      const url = document.createElement('p');
      url.className = 'muted truncate';
      url.textContent = bookmark.url || '';

      const path = document.createElement('p');
      path.className = 'muted truncate';
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

      item.append(title, url, path);
      list.append(item);
    });
  });

  more.hidden = bookmarkState.expanded || bookmarkState.results.length <= VISIBLE_RESULT_LIMIT;
  less.hidden = !bookmarkState.expanded || bookmarkState.results.length <= VISIBLE_RESULT_LIMIT;
}

function renderHistoryItems(): void {
  const list = requireElement(elements.historyList);
  const count = requireElement(elements.historyCount);
  const more = requireElement(elements.historyMore);
  const less = requireElement(elements.historyLess);
  list.textContent = '';

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
    groupTitle.className = 'group-title';
    groupTitle.textContent = deviceName;
    list.append(groupTitle);

    items.forEach((historyItem) => {
      const item = document.createElement('div');
      item.className = 'item clickable';
      item.tabIndex = 0;

      const title = document.createElement('div');
      title.className = 'truncate';
      title.textContent = historyItem.title || historyItem.url;

      const url = document.createElement('p');
      url.className = 'muted truncate';
      url.textContent = historyItem.url;

      const visitedAt = document.createElement('p');
      visitedAt.className = 'muted truncate';
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

      item.append(title, url, visitedAt);
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
      .catch(() => {
        bookmarkState.error = 'Could not load bookmarks.';
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
      .catch(() => {
        historyState.error = 'Could not load history.';
      })
      .finally(() => {
        if (requestId === historyState.requestId) {
          historyState.loading = false;
          renderHistoryItems();
        }
      });
  }, SEARCH_DEBOUNCE_MS);
}

function render(status: PopupStatus): void {
  latestStatus = status;

  const connectionStatus = requireElement(elements.connectionStatus);
  connectionStatus.textContent = status.configured ? 'Connected' : 'Disconnected';
  connectionStatus.className = `status ${status.configured ? 'ok' : 'bad'}`;

  requireElement(elements.deviceSummary).textContent = status.config.deviceName || 'No device configured';
  requireElement(elements.lastSync).textContent = status.config.lastSyncAt
    ? new Date(status.config.lastSyncAt).toLocaleString()
    : 'Never';

  requireElement(elements.toggleBookmarks).checked = status.config.sync.bookmarks;
  requireElement(elements.toggleTabs).checked = status.config.sync.tabs;

  const historyEnabled = status.config.sync.history;
  requireElement(elements.toggleHistory).checked = historyEnabled;
  requireElement(elements.historySettingsContainer).hidden = !historyEnabled;
  requireElement(elements.historySyncRange).value = status.config.historySyncRange || '24h';
  requireElement(elements.historyRangeWarning).hidden = status.config.historySyncRange !== 'all';

  requireElement(elements.currentTabTitle).textContent = status.currentTab?.title || 'No syncable current tab.';
  requireElement(elements.currentTabUrl).textContent = status.currentTab?.url || '';
  setError(status.config.lastError);
  setSendStatus(null);

  bookmarkState.all = status.bookmarks || [];
  bookmarkState.results = bookmarkState.query
    ? bookmarkState.all.filter((bookmark) => matchesBookmark(bookmark, bookmarkState.query))
    : bookmarkState.all;
  historyState.all = status.historyItems || [];
  historyState.results = historyState.query
    ? historyState.all.filter((historyItem) => matchesHistory(historyItem, historyState.query))
    : historyState.all;

  requireElement(elements.bookmarksStat).textContent = String(bookmarkState.all.length);
  requireElement(elements.historyStat).textContent = String(historyState.all.length);
  requireElement(elements.tabsStat).textContent = String(latestTabSnapshotCount(status.tabSnapshots));

  renderSendTargets(status);
  renderDevices(status);
  renderCommands(status);
  renderBookmarks();
  renderHistoryItems();
}

function renderSyncSummary(summary?: SyncSummary): void {
  if (!summary) {
    return;
  }

  const parts = [];

  if (summary.bookmarks) {
    parts.push(summary.bookmarks.success ? `Bookmarks: ${summary.bookmarks.count}` : 'Bookmarks: Failed');
  }

  if (summary.tabs) {
    parts.push(summary.tabs.success ? `Tabs: ${summary.tabs.count}` : 'Tabs: Failed');
  }

  if (summary.history) {
    if (summary.history.success) {
      const stored = summary.history.count;
      const skipped = summary.history.skipped || 0;
      const fetched = stored + skipped;
      parts.push(`History: fetched ${fetched}, stored ${stored}, skipped ${skipped}`);
    } else {
      parts.push('History: Failed');
    }
  }

  setError(parts.length > 0 ? `Sync finished. ${parts.join(' | ')}` : 'Sync finished but no categories were enabled.');
}

async function refresh(): Promise<void> {
  const status = await sendMessage<PopupStatus>({ type: 'browserbridge.getStatus' });
  render(status);
}

async function updateToggles(): Promise<void> {
  const config = await getConfig();
  const historyEnabled = requireElement(elements.toggleHistory).checked;

  await saveConfig({
    ...config,
    syncHistory: historyEnabled,
    sync: {
      bookmarks: requireElement(elements.toggleBookmarks).checked,
      tabs: requireElement(elements.toggleTabs).checked,
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

requireElement(elements.syncNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.syncNow' })
    .then((status) => {
      render(status);
      renderSyncSummary(status.summary);
    })
    .catch((error: unknown) => {
      console.error(error);
      setError('Sync failed. Check extension console for details.');
    });
});

requireElement(elements.sendCurrentTab).addEventListener('click', () => {
  const targetDeviceUuid = requireElement(elements.sendTarget).value;
  const targetName = requireElement(elements.sendTarget).selectedOptions[0]?.textContent || 'Safari';

  if (!targetDeviceUuid) {
    setSendStatus('No Safari device is available.');

    return;
  }

  void sendMessage<PopupStatus>({ type: 'browserbridge.sendCurrentTab', targetDeviceUuid })
    .then((nextStatus) => {
      render(nextStatus);
      showSentFeedback(`Sent to ${targetName}.`);
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to send tab.'));
});

requireElement(elements.syncBookmarksNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.syncBookmarksNow' })
    .then((status) => {
      render(status);
      renderSyncSummary(status.summary);
    })
    .catch((error: unknown) => {
      console.error(error);
      setError('Bookmark sync failed. Check extension console for details.');
    });
});

requireElement(elements.openOptions).addEventListener('click', () => {
  void chrome.runtime.openOptionsPage();
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
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to enable history sync.'));
});

[
  requireElement(elements.toggleBookmarks),
  requireElement(elements.toggleTabs),
].forEach((toggle) => {
  toggle.addEventListener('change', () => {
    void updateToggles().catch((error: unknown) => {
      setError(error instanceof Error ? error.message : 'Unable to update sync toggles.');
    });
  });
});

requireElement(elements.toggleHistory).addEventListener('change', () => {
  void handleHistoryToggle().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to update history sync.');
  });
});

requireElement(elements.historySyncRange).addEventListener('change', (event) => {
  const select = event.target as HTMLSelectElement;
  const value = select.value as HistorySyncRange;
  requireElement(elements.historyRangeWarning).hidden = value !== 'all';

  void getConfig()
    .then((config) => saveConfig({ ...config, historySyncRange: value }))
    .catch((error: unknown) => {
      setError(error instanceof Error ? error.message : 'Unable to update history sync range.');
    });
});

requireElement(elements.resyncHistory).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.resyncHistoryNow' })
    .then((status) => {
      render(status);
      renderSyncSummary(status.summary);
    })
    .catch((error: unknown) => {
      console.error(error);
      setError('Resync failed. Check extension console for details.');
    });
});

document.querySelectorAll<HTMLButtonElement>('[data-tab]').forEach((button) => {
  button.addEventListener('click', () => activateTab(button.dataset.tab || 'send'));
});

void refresh().catch((error: unknown) => {
  setError(error instanceof Error ? error.message : 'Unable to load BrowserBridge status.');
});
