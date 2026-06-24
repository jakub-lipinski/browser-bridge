import './modules/initSafariAdapter';
import {
  fetchBookmarks,
  fetchDevices,
  fetchTabSnapshots,
  searchHistory,
  uploadTabSnapshot,
} from '../../chrome-extension/src/modules/apiClient';
import { getBrowserAdapter, type BrowserCapabilityAudit } from '../../chrome-extension/src/modules/browserAdapter';
import { syncBookmarks } from '../../chrome-extension/src/modules/bookmarksSync';
import { connectDevice } from '../../chrome-extension/src/modules/device';
import { syncHistory } from '../../chrome-extension/src/modules/historySync';
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
import {
  activityItemFromTab,
  activityItemFromUrl,
  SAFARI_ACTIVITY_STORAGE_KEY,
  uniqueRecentActivity,
  type SafariActivityItem,
  type SafariActivitySource,
} from './modules/safariActivity';

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
  capabilityAudit: BrowserCapabilityAudit;
  refreshSummary?: SafariRefreshSummary;
};

const SAFARI_BOOKMARKS_UNAVAILABLE = 'Native Safari bookmark reading is not available in this Safari version.';
const SAFARI_HISTORY_UNAVAILABLE = 'Full native Safari history upload is not available in this Safari version. BrowserBridge can still save Safari pages you send/open through the extension.';

async function ensureRegistered(config: ExtensionConfig): Promise<ExtensionConfig> {
  if (!config.apiUrl || !config.apiToken) {
    throw new Error('BrowserBridge is not configured.');
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
  const capabilityAudit = await getBrowserAdapter().getCapabilityAudit();

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
      capabilityAudit,
    };
  }

  config = await ensureRegistered(config);
  const syncResult = await uploadSafariSources(config, capabilityAudit);

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
    bookmarksUploaded: syncResult.bookmarksUploaded,
    bookmarksUploadUnavailable: syncResult.bookmarksUploadUnavailable,
    historyUploaded: syncResult.historyUploaded,
    historyUploadMode: syncResult.historyUploadMode,
    historyUploadUnavailable: syncResult.historyUploadUnavailable,
    tabsUploaded: syncResult.tabsUploaded,
    devices: loadErrors.devices ? undefined : devices.length,
    incomingCommands: loadErrors.incomingCommands ? undefined : incomingCommands.length,
    remoteBookmarks: loadErrors.bookmarks ? undefined : bookmarks.length,
    remoteHistoryItems: loadErrors.historyItems ? undefined : historyItems.length,
    remoteTabSnapshots: loadErrors.tabSnapshots ? undefined : tabSnapshots.length,
    errors: loadErrors,
    unsupportedMessages: [
      ...(syncResult.bookmarksUploadUnavailable ? [syncResult.bookmarksUploadUnavailable] : []),
      ...(syncResult.historyUploadUnavailable ? [syncResult.historyUploadUnavailable] : []),
    ],
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
    capabilityAudit,
    refreshSummary: summary,
  };
}

type SafariSourceSyncResult = {
  bookmarksUploaded?: number;
  bookmarksUploadUnavailable?: string;
  historyUploaded?: number;
  historyUploadMode?: 'native' | 'activity' | 'unavailable';
  historyUploadUnavailable?: string;
  tabsUploaded?: number;
};

async function uploadSafariSources(config: ExtensionConfig, capabilityAudit: BrowserCapabilityAudit): Promise<SafariSourceSyncResult> {
  const result: SafariSourceSyncResult = {
    historyUploadMode: capabilityAudit.historyMode,
  };

  if (config.sync.bookmarks && capabilityAudit.canReadNativeBookmarks) {
    result.bookmarksUploaded = await syncBookmarks(config);
  } else if (config.sync.bookmarks) {
    result.bookmarksUploadUnavailable = SAFARI_BOOKMARKS_UNAVAILABLE;
  }

  const currentTab = capabilityAudit.canReadCurrentTab ? await getCurrentTab() : null;

  if (config.sync.history && capabilityAudit.historyMode === 'activity' && currentTab) {
    await recordSafariActivity(currentTab, 'manual_sync_current_tab');
  }

  if (config.sync.history) {
    if (capabilityAudit.canReadNativeHistory || capabilityAudit.historyMode === 'activity') {
      const historyResult = await syncHistory(config);
      result.historyUploaded = historyResult.count;
    } else {
      result.historyUploadUnavailable = SAFARI_HISTORY_UNAVAILABLE;
      result.historyUploadMode = 'unavailable';
    }
  } else if (!capabilityAudit.canReadNativeHistory) {
    result.historyUploadUnavailable = SAFARI_HISTORY_UNAVAILABLE;
  }

  if (capabilityAudit.canReadAllTabs) {
    const tabs = await getBrowserAdapter().getAllTabs();
    await uploadTabSnapshot(config, tabs);
    result.tabsUploaded = tabs.length;
  } else if (currentTab) {
    await uploadTabSnapshot(config, [currentTab]);
    result.tabsUploaded = 1;
  }

  return result;
}

async function captureError(error: unknown): Promise<void> {
  const message = error instanceof Error ? error.message : 'Unknown BrowserBridge error.';

  await updateConfig({ lastError: message });
}

async function getStatus() {
  const config = await getConfig();
  const capabilityAudit = await getBrowserAdapter().getCapabilityAudit();

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
      capabilityAudit,
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
    capabilityAudit,
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

      if (config.sync.history) {
        await recordSafariActivityFromUrl(message.command.url, message.command.title, 'opened_incoming_tab');
      }

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

      if (config.sync.history) {
        await recordSafariActivity(currentTab, 'sent_tab');
      }

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

async function recordSafariActivity(tab: TabSnapshotItem | null, source: SafariActivitySource): Promise<void> {
  const item = activityItemFromTab(tab, source);

  if (!item) {
    return;
  }

  await storeSafariActivity(item);
}

async function recordSafariActivityFromUrl(url: string | null | undefined, title: string | null | undefined, source: SafariActivitySource): Promise<void> {
  const item = activityItemFromUrl(url, title, source);

  if (!item) {
    return;
  }

  await storeSafariActivity(item);
}

async function storeSafariActivity(item: SafariActivityItem): Promise<void> {
  const adapter = getBrowserAdapter();
  const existing = await adapter.getStorage<SafariActivityItem[]>(SAFARI_ACTIVITY_STORAGE_KEY) || [];

  await adapter.setStorage(SAFARI_ACTIVITY_STORAGE_KEY, uniqueRecentActivity([item, ...existing]));
}
