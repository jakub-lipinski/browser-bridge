import './modules/initSafariAdapter';
import '../../chrome-extension/src/styles.css';
import { getBrowserAdapter } from '../../chrome-extension/src/modules/browserAdapter';
import { deleteSyncedHistory, searchBookmarks, searchHistory } from '../../chrome-extension/src/modules/apiClient';
import { getConfig } from '../../chrome-extension/src/modules/storage';
import type {
  DeviceResource,
  ExtensionConfig,
  HistoryItemResource,
  NormalizedBookmarkResource,
  TabCommandResource,
  TabSnapshotItem,
  TabSnapshotResource,
} from '../../chrome-extension/src/modules/types';

type SafariLoadErrorKey = 'devices' | 'incomingCommands' | 'bookmarks' | 'historyItems' | 'tabSnapshots';
type SafariLoadErrors = Partial<Record<SafariLoadErrorKey, string>>;

type SafariRefreshSummary = {
  refreshedAt: string;
  devices?: number;
  incomingCommands?: number;
  bookmarks?: number;
  historyItems?: number;
  tabSnapshots?: number;
  errors: SafariLoadErrors;
  unsupportedMessage: string;
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
  refreshSummary?: SafariRefreshSummary;
};

type RuntimeResponse<T> = {
  ok: boolean;
  payload?: T;
  error?: string;
};

const elements = {
  connectionStatus: document.querySelector<HTMLSpanElement>('#connection-status'),
  deviceSummary: document.querySelector<HTMLParagraphElement>('#device-summary'),
  lastSync: document.querySelector<HTMLParagraphElement>('#last-sync'),
  errorMessage: document.querySelector<HTMLParagraphElement>('#error-message'),
  syncNow: document.querySelector<HTMLButtonElement>('#sync-now'),
  sendCurrentTab: document.querySelector<HTMLButtonElement>('#send-current-tab'),
  currentTabTitle: document.querySelector<HTMLParagraphElement>('#current-tab-title'),
  currentTabUrl: document.querySelector<HTMLParagraphElement>('#current-tab-url'),
  devicesList: document.querySelector<HTMLDivElement>('#devices-list'),
  bookmarksList: document.querySelector<HTMLDivElement>('#bookmarks-list'),
  bookmarksQuery: document.querySelector<HTMLInputElement>('#bookmarks-query'),
  historyList: document.querySelector<HTMLDivElement>('#history-list'),
  historyQuery: document.querySelector<HTMLInputElement>('#history-query'),
  deleteHistory: document.querySelector<HTMLButtonElement>('#delete-history'),
  commandsList: document.querySelector<HTMLDivElement>('#commands-list'),
  refreshSummary: document.querySelector<HTMLParagraphElement>('#refresh-summary'),
  openOptions: document.querySelector<HTMLButtonElement>('#open-options'),
};

const SAFARI_UNSUPPORTED_MESSAGE = 'Safari currently displays BrowserBridge data from your server. Native Safari bookmark/history upload is not implemented in this version.';

let latestStatus: PopupStatus | null = null;

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

function loadErrorMessage(status: PopupStatus): string | null {
  const loadErrors = Object.values(status.loadErrors || {});

  if (loadErrors.length > 0) {
    return loadErrors.join(' | ');
  }

  if (status.config.lastError && status.config.lastError !== SAFARI_UNSUPPORTED_MESSAGE) {
    return status.config.lastError;
  }

  return null;
}

function crossBrowserTargets(status: PopupStatus): DeviceResource[] {
  return status.devices.filter((device) => {
    return device.uuid !== status.config.deviceUuid && device.browser === 'chrome';
  });
}

function renderDevices(status: PopupStatus): void {
  const devicesList = requireElement(elements.devicesList);
  devicesList.textContent = '';

  if (status.loadErrors.devices) {
    devicesList.innerHTML = '<p class="muted">Could not load devices</p>';

    return;
  }

  const otherDevices = crossBrowserTargets(status);

  if (otherDevices.length === 0) {
    devicesList.innerHTML = '<p class="muted">No Chrome device connected yet.</p>';

    return;
  }

  otherDevices.forEach((device) => {
    const item = document.createElement('div');
    item.className = 'item';

    const title = document.createElement('div');
    title.className = 'truncate';
    title.textContent = `${device.name} (${device.browser} on ${device.platform})`;

    const meta = document.createElement('p');
    meta.className = 'muted truncate';
    meta.textContent = device.last_seen_at ? `Last seen ${new Date(device.last_seen_at).toLocaleString()}` : 'Never seen';

    const sendButton = document.createElement('button');
    sendButton.type = 'button';
    sendButton.textContent = 'Send current tab to Chrome';
    sendButton.disabled = !status.currentTab;
    sendButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({
        type: 'browserbridge.sendCurrentTab',
        targetDeviceUuid: device.uuid,
      })
        .then((nextStatus) => {
          render(nextStatus);
          setError(`Sent current tab to ${device.name}.`);
        })
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to send tab.'));
    });

    item.append(title, meta, sendButton);
    devicesList.append(item);
  });
}

function renderCommands(status: PopupStatus): void {
  const commandsList = requireElement(elements.commandsList);
  commandsList.textContent = '';

  if (status.loadErrors.incomingCommands) {
    commandsList.innerHTML = '<p class="muted">Could not load incoming tabs</p>';

    return;
  }

  if (status.incomingCommands.length === 0) {
    commandsList.innerHTML = '<p class="muted">No incoming tab commands.</p>';

    return;
  }

  status.incomingCommands.forEach((command) => {
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
      void sendMessage<PopupStatus>({
        type: 'browserbridge.openCommand',
        command,
      })
        .then(render)
        .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to open command.'));
    });

    const dismissButton = document.createElement('button');
    dismissButton.type = 'button';
    dismissButton.className = 'secondary';
    dismissButton.textContent = 'Dismiss';
    dismissButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({
        type: 'browserbridge.dismissCommand',
        commandId: command.id,
      })
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

function renderBookmarks(bookmarks: NormalizedBookmarkResource[] = [], error?: string): void {
  const bookmarksList = requireElement(elements.bookmarksList);
  bookmarksList.textContent = '';

  if (error) {
    bookmarksList.innerHTML = '<p class="muted">Could not load bookmarks</p>';

    return;
  }

  if (bookmarks.length === 0) {
    bookmarksList.innerHTML = '<p class="muted">No BrowserBridge bookmarks available yet. Upload bookmarks from Chrome first.</p>';

    return;
  }

  groupBookmarksByDevice(bookmarks).forEach((items, deviceName) => {
    const groupTitle = document.createElement('div');
    groupTitle.className = 'group-title';
    groupTitle.textContent = deviceName;
    bookmarksList.append(groupTitle);

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
      bookmarksList.append(item);
    });
  });
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

function renderHistoryItems(historyItems: HistoryItemResource[] = [], error?: string): void {
  const historyList = requireElement(elements.historyList);
  historyList.textContent = '';

  if (error) {
    historyList.innerHTML = '<p class="muted">Could not load history</p>';

    return;
  }

  if (historyItems.length === 0) {
    historyList.innerHTML = '<p class="muted">No BrowserBridge history available yet. Enable history sync in Chrome first.</p>';

    return;
  }

  groupHistoryItemsByDevice(historyItems).forEach((items, deviceName) => {
    const groupTitle = document.createElement('div');
    groupTitle.className = 'group-title';
    groupTitle.textContent = deviceName;
    historyList.append(groupTitle);

    items.forEach((historyItem) => {
      const item = document.createElement('div');
      item.className = 'item clickable';
      item.tabIndex = 0;

      const title = document.createElement('div');
      title.className = 'truncate';
      title.textContent = historyItem.title || historyItem.url;

      const meta = document.createElement('p');
      meta.className = 'muted truncate';
      meta.textContent = historyItem.url;

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

      item.append(title, meta);
      historyList.append(item);
    });
  });
}

function render(status: PopupStatus): void {
  latestStatus = status;

  const connectionStatus = requireElement(elements.connectionStatus);
  connectionStatus.textContent = status.configured ? 'Connected' : 'Disconnected';
  connectionStatus.className = `status ${status.configured ? 'ok' : 'bad'}`;

  requireElement(elements.deviceSummary).textContent = status.config.deviceName || 'No Safari device configured';
  requireElement(elements.lastSync).textContent = status.config.lastSyncAt
    ? new Date(status.config.lastSyncAt).toLocaleString()
    : 'Never';

  requireElement(elements.currentTabTitle).textContent = status.currentTab?.title || 'No syncable current tab.';
  requireElement(elements.currentTabUrl).textContent = status.currentTab?.url || '';
  requireElement(elements.sendCurrentTab).disabled = !status.currentTab || crossBrowserTargets(status).length === 0;
  setError(loadErrorMessage(status));

  renderDevices(status);
  renderBookmarks(status.bookmarks, status.loadErrors.bookmarks);
  renderHistoryItems(status.historyItems, status.loadErrors.historyItems);
  renderCommands(status);
}

function formatRefreshPart(label: string, value: number | undefined, error?: string): string {
  if (error) {
    return `${label}: failed`;
  }

  return `${label}: ${value ?? 0}`;
}

function renderRefreshSummary(summary?: SafariRefreshSummary): void {
  const summaryElement = requireElement(elements.refreshSummary);

  if (!summary) {
    return;
  }

  const hasErrors = Object.keys(summary.errors).length > 0;
  const parts = [
    formatRefreshPart('Devices', summary.devices, summary.errors.devices),
    formatRefreshPart('Incoming tabs', summary.incomingCommands, summary.errors.incomingCommands),
    formatRefreshPart('Bookmarks loaded', summary.bookmarks, summary.errors.bookmarks),
    formatRefreshPart('History loaded', summary.historyItems, summary.errors.historyItems),
    formatRefreshPart('Tab snapshots', summary.tabSnapshots, summary.errors.tabSnapshots),
  ];

  summaryElement.textContent = `${hasErrors ? 'Refresh completed with errors.' : 'Refresh complete.'} ${parts.join(' | ')}`;
}

async function refresh(): Promise<void> {
  const status = await sendMessage<PopupStatus>({ type: 'browserbridge.getStatus' });
  render(status);
}

async function searchBrowserBridgeHistory(): Promise<void> {
  const config = await getConfig();
  const query = requireElement(elements.historyQuery).value.trim();

  renderHistoryItems(await searchHistory(config, query));
}

async function searchBrowserBridgeBookmarks(): Promise<void> {
  const config = await getConfig();
  const query = requireElement(elements.bookmarksQuery).value.trim();

  renderBookmarks(await searchBookmarks(config, query));
}

async function deleteBrowserBridgeHistory(): Promise<void> {
  const config = await getConfig();

  await deleteSyncedHistory(config);
  renderHistoryItems([]);
  setError('Deleted synced BrowserBridge History.');
}

requireElement(elements.syncNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.refreshNow' })
    .then((status) => {
      render(status);
      renderRefreshSummary(status.refreshSummary);
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to refresh BrowserBridge data.'));
});

requireElement(elements.sendCurrentTab).addEventListener('click', () => {
  const target = latestStatus ? crossBrowserTargets(latestStatus)[0] : null;

  if (!target) {
    setError('No Chrome device is available for sending this tab.');

    return;
  }

  void sendMessage<PopupStatus>({
    type: 'browserbridge.sendCurrentTab',
    targetDeviceUuid: target.uuid,
  })
    .then((nextStatus) => {
      render(nextStatus);
      setError(`Sent current tab to ${target.name}.`);
    })
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to send tab.'));
});

requireElement(elements.openOptions).addEventListener('click', () => {
  void chrome.runtime.openOptionsPage();
});

requireElement(elements.historyQuery).addEventListener('input', () => {
  void searchBrowserBridgeHistory().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to search BrowserBridge History.');
  });
});

requireElement(elements.bookmarksQuery).addEventListener('input', () => {
  void searchBrowserBridgeBookmarks().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to search BrowserBridge Bookmarks.');
  });
});

requireElement(elements.deleteHistory).addEventListener('click', () => {
  if (!confirm('Delete all synced BrowserBridge History from the server?')) {
    return;
  }

  void deleteBrowserBridgeHistory().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to delete BrowserBridge History.');
  });
});

void refresh().catch((error: unknown) => {
  setError(error instanceof Error ? error.message : 'Unable to load BrowserBridge Safari status.');
});
