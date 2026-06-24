import './modules/initSafariAdapter';
import {
  fetchBookmarks,
  fetchDevices,
  fetchTabSnapshots,
  searchHistory,
} from '../../chrome-extension/src/modules/apiClient';
import { connectDevice } from '../../chrome-extension/src/modules/device';
import { getConfig, isConfigured, updateConfig } from '../../chrome-extension/src/modules/storage';
import { getCurrentTab } from '../../chrome-extension/src/modules/tabsSync';
import {
  dismissIncomingCommand,
  getIncomingCommands,
  openIncomingCommand,
  sendCurrentTabToDevice,
} from '../../chrome-extension/src/modules/tabCommands';
import type {
  DeviceResource,
  ExtensionConfig,
  HistoryItemResource,
  NormalizedBookmarkResource,
  TabCommandResource,
  TabSnapshotItem,
  TabSnapshotResource,
} from '../../chrome-extension/src/modules/types';

const SYNC_ALARM_NAME = 'browserbridge.sync';
const SYNC_INTERVAL_MINUTES = 1;

type RuntimeMessage =
  | { type: 'browserbridge.syncNow' }
  | { type: 'browserbridge.refreshNow' }
  | { type: 'browserbridge.getStatus' }
  | { type: 'browserbridge.openCommand'; command: TabCommandResource }
  | { type: 'browserbridge.dismissCommand'; commandId: number }
  | { type: 'browserbridge.sendCurrentTab'; targetDeviceUuid: string };

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

type SafariStatus = {
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

const SAFARI_UNSUPPORTED_MESSAGE = 'Safari currently displays BrowserBridge data from your server. Native Safari bookmark/history upload is not implemented in this version.';

async function ensureRegistered(config: ExtensionConfig): Promise<ExtensionConfig> {
  if (!config.apiUrl || !config.apiToken) {
    throw new Error('BrowserBridge is not configured.');
  }

  if (config.deviceUuid) {
    return config;
  }

  return connectDevice(config);
}

function friendlyLoadError(key: SafariLoadErrorKey): string {
  return {
    devices: 'Could not load devices',
    incomingCommands: 'Could not load incoming tabs',
    bookmarks: 'Could not load bookmarks',
    historyItems: 'Could not load history',
    tabSnapshots: 'Could not load tab snapshots',
  }[key];
}

async function loadSection<T>(
  key: SafariLoadErrorKey,
  loadErrors: SafariLoadErrors,
  loader: () => Promise<T[]>,
): Promise<T[]> {
  try {
    return await loader();
  } catch (error) {
    console.warn(`[BrowserBridge Safari] ${friendlyLoadError(key)}:`, error);
    loadErrors[key] = friendlyLoadError(key);

    return [];
  }
}

async function refreshOnce(): Promise<SafariStatus> {
  let config = await getConfig();

  if (!config.apiUrl || !config.apiToken) {
    return {
      config,
      configured: false,
      devices: [],
      incomingCommands: [],
      currentTab: await getCurrentTab(),
      bookmarks: [],
      historyItems: [],
      tabSnapshots: [],
      loadErrors: {},
    };
  }

  config = await ensureRegistered(config);

  const loadErrors: SafariLoadErrors = {};
  const [devices, incomingCommands, currentTab, bookmarks, historyItems, tabSnapshots] = await Promise.all([
    loadSection('devices', loadErrors, () => fetchDevices(config)),
    loadSection('incomingCommands', loadErrors, () => getIncomingCommands(config)),
    getCurrentTab(),
    loadSection('bookmarks', loadErrors, () => fetchBookmarks(config)),
    loadSection('historyItems', loadErrors, () => searchHistory(config)),
    loadSection('tabSnapshots', loadErrors, () => fetchTabSnapshots(config)),
  ]);

  const refreshedAt = new Date().toISOString();
  const summary: SafariRefreshSummary = {
    refreshedAt,
    devices: loadErrors.devices ? undefined : devices.length,
    incomingCommands: loadErrors.incomingCommands ? undefined : incomingCommands.length,
    bookmarks: loadErrors.bookmarks ? undefined : bookmarks.length,
    historyItems: loadErrors.historyItems ? undefined : historyItems.length,
    tabSnapshots: loadErrors.tabSnapshots ? undefined : tabSnapshots.length,
    errors: loadErrors,
    unsupportedMessage: SAFARI_UNSUPPORTED_MESSAGE,
  };

  await updateConfig({
    lastSyncAt: refreshedAt,
    lastError: Object.keys(loadErrors).length > 0 ? Object.values(loadErrors).join(' | ') : null,
  });

  const refreshedConfig = await getConfig();

  return {
    config: refreshedConfig,
    configured: true,
    devices,
    incomingCommands,
    currentTab,
    bookmarks,
    historyItems,
    tabSnapshots,
    loadErrors,
    refreshSummary: summary,
  };
}

async function captureError(error: unknown): Promise<void> {
  const message = error instanceof Error ? error.message : 'Unknown BrowserBridge error.';

  await updateConfig({ lastError: message });
}

async function getStatus() {
  const config = await getConfig();

  if (!isConfigured(config)) {
    return {
      config,
      configured: false,
      devices: [],
      incomingCommands: [],
      currentTab: await getCurrentTab(),
      bookmarks: [],
      historyItems: [],
      tabSnapshots: [],
      loadErrors: {},
    };
  }

  const loadErrors: SafariLoadErrors = {};
  const [devices, incomingCommands, currentTab, bookmarks, historyItems, tabSnapshots] = await Promise.all([
    loadSection('devices', loadErrors, () => fetchDevices(config)),
    loadSection('incomingCommands', loadErrors, () => getIncomingCommands(config)),
    getCurrentTab(),
    loadSection('bookmarks', loadErrors, () => fetchBookmarks(config)),
    loadSection('historyItems', loadErrors, () => searchHistory(config)),
    loadSection('tabSnapshots', loadErrors, () => fetchTabSnapshots(config)),
  ]);

  return {
    config,
    configured: true,
    devices,
    incomingCommands,
    currentTab,
    bookmarks,
    historyItems,
    tabSnapshots,
    loadErrors,
  };
}

function installAlarm(): void {
  try {
    chrome.alarms?.create(SYNC_ALARM_NAME, {
      delayInMinutes: 0.1,
      periodInMinutes: SYNC_INTERVAL_MINUTES,
    });
  } catch {
    void captureError(new Error('Safari background polling is unavailable. Open the popup to sync manually.'));
  }
}

chrome.runtime.onInstalled.addListener(installAlarm);

chrome.runtime.onStartup?.addListener(() => {
  void refreshOnce().catch(captureError);
});

chrome.alarms?.onAlarm.addListener((alarm) => {
  if (alarm.name === SYNC_ALARM_NAME) {
    void refreshOnce().catch(captureError);
  }
});

chrome.runtime.onMessage.addListener((message: RuntimeMessage, _sender, sendResponse) => {
  const respond = async (): Promise<unknown> => {
    if (message.type === 'browserbridge.syncNow' || message.type === 'browserbridge.refreshNow') {
      return refreshOnce();
    }

    if (message.type === 'browserbridge.getStatus') {
      return getStatus();
    }

    const config = await getConfig();

    if (message.type === 'browserbridge.openCommand') {
      await openIncomingCommand(config, message.command);

      return getStatus();
    }

    if (message.type === 'browserbridge.dismissCommand') {
      await dismissIncomingCommand(config, message.commandId);

      return getStatus();
    }

    if (message.type === 'browserbridge.sendCurrentTab') {
      const currentTab = await getCurrentTab();

      if (!currentTab) {
        throw new Error('Safari could not read the current active tab.');
      }

      await sendCurrentTabToDevice(config, message.targetDeviceUuid, currentTab);

      return getStatus();
    }

    throw new Error('Unknown BrowserBridge message.');
  };

  respond()
    .then((payload) => sendResponse({ ok: true, payload }))
    .catch((error: unknown) => {
      void captureError(error);
      sendResponse({
        ok: false,
        error: error instanceof Error ? error.message : 'Unknown BrowserBridge error.',
      });
    });

  return true;
});
