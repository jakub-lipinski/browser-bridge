import './modules/initChromiumAdapter';
import {
  fetchBookmarks,
  fetchBookmarkSyncProfiles,
  fetchDevices,
  fetchTabSnapshots,
  previewBookmarkSyncProfile,
  runBookmarkSyncProfile,
  searchHistory,
} from './modules/apiClient';
import { applyNativeBookmarkSync } from './modules/bookmarkSyncModes';
import { syncBookmarks } from './modules/bookmarksSync';
import { connectDevice } from './modules/device';
import { getBrowserAdapter } from './modules/browserAdapter';
import { syncHistory } from './modules/historySync';
import { getConfig, isConfigured, updateConfig } from './modules/storage';
import { getCurrentTab, syncOpenTabs } from './modules/tabsSync';
import {
  dismissIncomingCommand,
  getIncomingCommands,
  openIncomingCommand,
  sendCurrentTabToDevice,
} from './modules/tabCommands';
import type { ExtensionConfig, SyncSummary, TabCommandResource } from './modules/types';

const SYNC_ALARM_NAME = 'browserbridge.sync';
const SYNC_INTERVAL_MINUTES = 1;
const SENT_BADGE_DURATION_MS = 1800;

type RuntimeMessage =
  | { type: 'browserbridge.syncNow' }
  | { type: 'browserbridge.syncBookmarksNow' }
  | { type: 'browserbridge.resyncHistoryNow' }
  | { type: 'browserbridge.getStatus' }
  | { type: 'browserbridge.openCommand'; command: TabCommandResource }
  | { type: 'browserbridge.dismissCommand'; commandId: number }
  | { type: 'browserbridge.sendCurrentTab'; targetDeviceUuid: string };

async function ensureRegistered(config: ExtensionConfig): Promise<ExtensionConfig> {
  if (!config.apiUrl || !config.apiToken) {
    throw new Error('BrowserBridge is not configured.');
  }

  if (config.deviceUuid) {
    return config;
  }

  return connectDevice(config);
}

async function setActionBadgeCount(count: number): Promise<void> {
  if (!chrome.action) {
    return;
  }

  await chrome.action.setBadgeBackgroundColor({ color: '#0f766e' });
  await chrome.action.setBadgeText({ text: count > 0 ? String(count) : '' });
}

async function refreshActionBadge(config?: ExtensionConfig): Promise<void> {
  config = config ?? await getConfig();

  if (!isConfigured(config)) {
    await setActionBadgeCount(0);

    return;
  }

  const incomingCommands = await getIncomingCommands(config);
  await setActionBadgeCount(incomingCommands.length);
}

function flashSentBadge(): void {
  if (!chrome.action) {
    return;
  }

  void chrome.action.setBadgeBackgroundColor({ color: '#0f766e' });
  void chrome.action.setBadgeText({ text: '✓' });

  setTimeout(() => {
    void refreshActionBadge().catch((error: unknown) => {
      console.warn('[BrowserBridge] Unable to refresh action badge:', error);
    });
  }, SENT_BADGE_DURATION_MS);
}

async function syncOnce(): Promise<SyncSummary> {
  let config = await getConfig();

  if (!config.apiUrl || !config.apiToken) {
    return {};
  }

  config = await ensureRegistered(config);

  const summary: SyncSummary = {};
  let globalError: string | null = null;

  if (config.sync.bookmarks) {
    try {
      const count = await syncBookmarks(config);
      summary.bookmarks = { success: true, count };
    } catch (error) {
      summary.bookmarks = { success: false, count: 0, error: error instanceof Error ? error.message : 'Unknown error' };
      globalError = summary.bookmarks.error || globalError;
      console.warn('[BrowserBridge] Bookmark sync failed:', error);
    }
  }

  if (config.sync.tabs) {
    try {
      const count = await syncOpenTabs(config);
      summary.tabs = { success: true, count };
    } catch (error) {
      summary.tabs = { success: false, count: 0, error: error instanceof Error ? error.message : 'Unknown error' };
      globalError = summary.tabs.error || globalError;
      console.warn('[BrowserBridge] Tab sync failed:', error);
    }
  }

  if (config.sync.history) {
    try {
      const result = await syncHistory(config);
      summary.history = { success: true, count: result.count, skipped: result.skipped };
    } catch (error) {
      summary.history = { success: false, count: 0, error: error instanceof Error ? error.message : 'Unknown error' };
      globalError = summary.history.error || globalError;
      console.warn('[BrowserBridge] History sync failed:', error);
    }
  }

  try {
    await runDueBookmarkSyncProfiles(config);
  } catch (error) {
    globalError = error instanceof Error ? error.message : 'Unknown bookmark sync profile error';
    console.warn('[BrowserBridge] Bookmark sync profiles failed:', error);
  }

  try {
    const incomingCommands = await getIncomingCommands(config);
    await setActionBadgeCount(incomingCommands.length);
  } catch (error) {
    globalError = error instanceof Error ? error.message : 'Unknown error';
    console.warn('[BrowserBridge] Incoming commands sync failed:', error);
  }

  await updateConfig({
    lastSyncAt: new Date().toISOString(),
    lastError: globalError,
  });

  return summary;
}

async function runDueBookmarkSyncProfiles(config: ExtensionConfig): Promise<void> {
  if (!getBrowserAdapter().supportsNativeBookmarkWrite()) {
    return;
  }

  const profiles = await fetchBookmarkSyncProfiles(config);
  const now = Date.now();

  for (const profile of profiles) {
    const isCurrentTarget = profile.target_device?.uuid === config.deviceUuid;
    const isDue = profile.auto_sync_enabled && profile.next_run_at && new Date(profile.next_run_at).getTime() <= now;

    if (!profile.is_active || !isCurrentTarget || !isDue) {
      continue;
    }

    if (profile.mode === 'mirror') {
      console.warn('[BrowserBridge] Skipping automatic Mirror bookmark sync; run Mirror manually from options.');
      continue;
    }

    await previewBookmarkSyncProfile(config, profile.id);

    const sourceBookmarks = await fetchBookmarks(config, profile.source_device_id);
    const result = await applyNativeBookmarkSync(profile, sourceBookmarks);

    await runBookmarkSyncProfile(config, profile.id, {
      operation_log: result.operationLog,
      result: {
        native_preview: result.preview,
        auto_sync: true,
      },
    });
  }
}

async function captureError(error: unknown): Promise<void> {
  const message = error instanceof Error ? error.message : 'Unknown BrowserBridge error.';

  await updateConfig({ lastError: message });
}

async function getStatus(updateBadge = true) {
  const config = await getConfig();

  if (!isConfigured(config)) {
    if (updateBadge) {
      await setActionBadgeCount(0);
    }

    return {
      config,
      configured: false,
      devices: [],
      incomingCommands: [],
      currentTab: await getCurrentTab(),
    };
  }

  const [devices, incomingCommands, currentTab, bookmarks, historyItems, tabSnapshots] = await Promise.all([
    fetchDevices(config).catch((error) => {
      console.warn('[BrowserBridge] Could not load devices:', error);
      return [];
    }),
    getIncomingCommands(config).catch((error) => {
      console.warn('[BrowserBridge] Could not load incoming commands:', error);
      return [];
    }),
    getCurrentTab().catch((error) => {
      console.warn('[BrowserBridge] Could not load current tab:', error);
      return null;
    }),
    fetchBookmarks(config).catch((error) => {
      console.warn('[BrowserBridge] Could not load bookmarks:', error);
      return [];
    }),
    searchHistory(config).catch((error) => {
      console.warn('[BrowserBridge] Could not load history:', error);
      return [];
    }),
    fetchTabSnapshots(config).catch((error) => {
      console.warn('[BrowserBridge] Could not load tab snapshots:', error);
      return [];
    }),
  ]);

  if (updateBadge) {
    await setActionBadgeCount(incomingCommands.length);
  }

  return {
    config,
    configured: true,
    devices,
    incomingCommands,
    currentTab,
    bookmarks,
    historyItems,
    tabSnapshots,
  };
}

chrome.runtime.onInstalled.addListener(() => {
  chrome.alarms.create(SYNC_ALARM_NAME, {
    delayInMinutes: 0.1,
    periodInMinutes: SYNC_INTERVAL_MINUTES,
  });
});

chrome.runtime.onStartup.addListener(() => {
  void syncOnce().catch(captureError);
});

chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === SYNC_ALARM_NAME) {
    void syncOnce().catch(captureError);
  }
});

chrome.runtime.onMessage.addListener((message: RuntimeMessage, _sender, sendResponse) => {
  const respond = async (): Promise<unknown> => {
    if (message.type === 'browserbridge.syncNow') {
      const summary = await syncOnce();
      const status = await getStatus();

      return { ...status, summary };
    }

    if (message.type === 'browserbridge.syncBookmarksNow') {
      const config = await ensureRegistered(await getConfig());
      const summary: SyncSummary = {};

      try {
        const count = await syncBookmarks(config);
        summary.bookmarks = { success: true, count };
      } catch (error) {
        summary.bookmarks = { success: false, count: 0, error: error instanceof Error ? error.message : 'Unknown error' };
        throw error; // Let the popup catch it
      }

      const status = await getStatus();

      return { ...status, summary };
    }

    if (message.type === 'browserbridge.resyncHistoryNow') {
      const config = await ensureRegistered(await getConfig());
      const summary: SyncSummary = {};

      try {
        const result = await syncHistory(config, true);
        summary.history = { success: true, count: result.count, skipped: result.skipped };
      } catch (error) {
        summary.history = { success: false, count: 0, error: error instanceof Error ? error.message : 'Unknown error' };
        throw error; // Let the popup catch it
      }

      const status = await getStatus();

      return { ...status, summary };
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
        throw new Error('The current tab cannot be sent.');
      }

      await sendCurrentTabToDevice(config, message.targetDeviceUuid, currentTab);
      const status = await getStatus(false);
      flashSentBadge();

      return status;
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
