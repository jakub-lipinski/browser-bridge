import './styles.css';
import { getConfig, saveConfig } from './modules/storage';
import type { DeviceResource, ExtensionConfig, TabCommandResource, TabSnapshotItem } from './modules/types';

type PopupStatus = {
  config: ExtensionConfig;
  configured: boolean;
  devices: DeviceResource[];
  incomingCommands: TabCommandResource[];
  currentTab: TabSnapshotItem | null;
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
  commandsList: document.querySelector<HTMLDivElement>('#commands-list'),
  openOptions: document.querySelector<HTMLButtonElement>('#open-options'),
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

function renderDevices(status: PopupStatus): void {
  const devicesList = requireElement(elements.devicesList);
  devicesList.textContent = '';

  const otherDevices = status.devices.filter((device) => device.uuid !== status.config.deviceUuid);

  if (otherDevices.length === 0) {
    devicesList.innerHTML = '<p class="muted">No other devices connected yet.</p>';

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
    sendButton.textContent = 'Send current tab';
    sendButton.disabled = !status.currentTab;
    sendButton.addEventListener('click', () => {
      void sendMessage<PopupStatus>({
        type: 'browserbridge.sendCurrentTab',
        targetDeviceUuid: device.uuid,
      })
        .then(render)
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
  renderCommands(status);
}

async function refresh(): Promise<void> {
  const status = await sendMessage<PopupStatus>({ type: 'browserbridge.getStatus' });
  render(status);
}

async function updateToggles(): Promise<void> {
  const config = await getConfig();

  await saveConfig({
    ...config,
    sync: {
      bookmarks: requireElement(elements.toggleBookmarks).checked,
      tabs: requireElement(elements.toggleTabs).checked,
      history: requireElement(elements.toggleHistory).checked,
    },
  });

  await refresh();
}

requireElement(elements.syncNow).addEventListener('click', () => {
  void sendMessage<PopupStatus>({ type: 'browserbridge.syncNow' })
    .then(render)
    .catch((error: unknown) => setError(error instanceof Error ? error.message : 'Unable to sync.'));
});

requireElement(elements.openOptions).addEventListener('click', () => {
  void chrome.runtime.openOptionsPage();
});

[
  requireElement(elements.toggleBookmarks),
  requireElement(elements.toggleTabs),
  requireElement(elements.toggleHistory),
].forEach((toggle) => {
  toggle.addEventListener('change', () => {
    void updateToggles().catch((error: unknown) => {
      setError(error instanceof Error ? error.message : 'Unable to update sync toggles.');
    });
  });
});

void refresh().catch((error: unknown) => {
  setError(error instanceof Error ? error.message : 'Unable to load BrowserBridge status.');
});
