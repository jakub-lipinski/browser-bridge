import './modules/initChromiumAdapter';
import './styles.css';
import { getBrowserAdapter } from './modules/browserAdapter';
import { getConfig, saveConfig } from './modules/storage';
import { deleteSyncedHistory, searchHistory } from './modules/apiClient';
import type {
  BookmarkSnapshotResource,
  DeviceResource,
  ExtensionConfig,
  HistoryItemResource,
  TabCommandResource,
  TabSnapshotItem,
} from './modules/types';

type PopupStatus = {
  config: ExtensionConfig;
  configured: boolean;
  devices: DeviceResource[];
  incomingCommands: TabCommandResource[];
  currentTab: TabSnapshotItem | null;
  bookmarkSnapshots?: BookmarkSnapshotResource[];
  historyItems?: HistoryItemResource[];
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
  toggleBookmarks: document.querySelector<HTMLInputElement>('#toggle-bookmarks'),
  toggleTabs: document.querySelector<HTMLInputElement>('#toggle-tabs'),
  toggleHistory: document.querySelector<HTMLInputElement>('#toggle-history'),
  currentTabTitle: document.querySelector<HTMLParagraphElement>('#current-tab-title'),
  currentTabUrl: document.querySelector<HTMLParagraphElement>('#current-tab-url'),
  devicesList: document.querySelector<HTMLDivElement>('#devices-list'),
  bookmarksList: document.querySelector<HTMLDivElement>('#bookmarks-list'),
  historyList: document.querySelector<HTMLDivElement>('#history-list'),
  historyQuery: document.querySelector<HTMLInputElement>('#history-query'),
  deleteHistory: document.querySelector<HTMLButtonElement>('#delete-history'),
  commandsList: document.querySelector<HTMLDivElement>('#commands-list'),
  openOptions: document.querySelector<HTMLButtonElement>('#open-options'),
  historyConfirmationModal: document.querySelector<HTMLDivElement>('#history-confirmation-modal'),
  cancelHistoryEnable: document.querySelector<HTMLButtonElement>('#cancel-history-enable'),
  confirmHistoryEnable: document.querySelector<HTMLButtonElement>('#confirm-history-enable'),
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

function crossBrowserTargets(status: PopupStatus): DeviceResource[] {
  return status.devices.filter((device) => {
    return device.uuid !== status.config.deviceUuid && device.browser === 'safari';
  });
}

function renderDevices(status: PopupStatus): void {
  const devicesList = requireElement(elements.devicesList);
  devicesList.textContent = '';

  const otherDevices = crossBrowserTargets(status);

  if (otherDevices.length === 0) {
    devicesList.innerHTML = '<p class="muted">No Safari device connected yet.</p>';

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
    sendButton.textContent = 'Send current tab to Safari';
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

function renderBookmarks(status: PopupStatus): void {
  const bookmarksList = requireElement(elements.bookmarksList);
  bookmarksList.textContent = '';

  const bookmarks = (status.bookmarkSnapshots || []).flatMap((snapshot) => {
    return (snapshot.payload_json?.items || []).slice(0, 8).map((item) => ({
      ...item,
      deviceName: snapshot.device?.name || `Device ${snapshot.device_id}`,
    }));
  }).slice(0, 20);

  if (bookmarks.length === 0) {
    bookmarksList.innerHTML = '<p class="muted">No BrowserBridge bookmarks available yet.</p>';

    return;
  }

  bookmarks.forEach((bookmark) => {
    const item = document.createElement('div');
    item.className = 'item';

    const title = document.createElement('div');
    title.className = 'truncate';
    title.textContent = bookmark.title || bookmark.url;

    const url = document.createElement('p');
    url.className = 'muted truncate';
    url.textContent = `${bookmark.deviceName} - ${bookmark.url}`;

    item.append(title, url);
    bookmarksList.append(item);
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

function renderHistoryItems(historyItems: HistoryItemResource[] = []): void {
  const historyList = requireElement(elements.historyList);
  historyList.textContent = '';

  if (historyItems.length === 0) {
    historyList.innerHTML = '<p class="muted">No BrowserBridge history available yet.</p>';

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
  const connectionStatus = requireElement(elements.connectionStatus);
  connectionStatus.textContent = status.configured ? 'Connected' : 'Disconnected';
  connectionStatus.className = `status ${status.configured ? 'ok' : 'bad'}`;

  requireElement(elements.deviceSummary).textContent = status.config.deviceName || 'No device configured';
  requireElement(elements.lastSync).textContent = status.config.lastSyncAt
    ? new Date(status.config.lastSyncAt).toLocaleString()
    : 'Never';

  requireElement(elements.toggleBookmarks).checked = status.config.sync.bookmarks;
  requireElement(elements.toggleTabs).checked = status.config.sync.tabs;
  requireElement(elements.toggleHistory).checked = status.config.sync.history;

  requireElement(elements.currentTabTitle).textContent = status.currentTab?.title || 'No syncable current tab.';
  requireElement(elements.currentTabUrl).textContent = status.currentTab?.url || '';
  setError(status.config.lastError);

  renderDevices(status);
  renderBookmarks(status);
  renderHistoryItems(status.historyItems || []);
  renderCommands(status);
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

async function searchBrowserBridgeHistory(): Promise<void> {
  const config = await getConfig();
  const query = requireElement(elements.historyQuery).value.trim();

  renderHistoryItems(await searchHistory(config, query));
}

async function deleteBrowserBridgeHistory(): Promise<void> {
  const config = await getConfig();

  await deleteSyncedHistory(config);
  renderHistoryItems([]);
  setError('Deleted synced BrowserBridge History.');
}

requireElement(elements.syncNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.syncNow' })
    .then(render)
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to sync.'));
});

requireElement(elements.openOptions).addEventListener('click', () => {
  void chrome.runtime.openOptionsPage();
});

requireElement(elements.historyQuery).addEventListener('input', () => {
  void searchBrowserBridgeHistory().catch((error: unknown) => {
    setError(error instanceof Error ? error.message : 'Unable to search BrowserBridge History.');
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

void refresh().catch((error: unknown) => {
  setError(error instanceof Error ? error.message : 'Unable to load BrowserBridge status.');
});
