import './modules/initSafariAdapter';
import {
  fetchBookmarkSnapshots,
  fetchDevices,
  searchHistory,
} from '../../chrome-extension/src/modules/apiClient';
import { connectDevice } from '../../chrome-extension/src/modules/device';
import { syncHistory } from '../../chrome-extension/src/modules/historySync';
import { getConfig, isConfigured, updateConfig } from '../../chrome-extension/src/modules/storage';
import { getCurrentTab, syncOpenTabs } from '../../chrome-extension/src/modules/tabsSync';
import {
  dismissIncomingCommand,
  getIncomingCommands,
  openIncomingCommand,
  sendCurrentTabToDevice,
} from '../../chrome-extension/src/modules/tabCommands';
import type { ExtensionConfig, TabCommandResource } from '../../chrome-extension/src/modules/types';

const SYNC_ALARM_NAME = 'browserbridge.sync';
const SYNC_INTERVAL_MINUTES = 1;

type RuntimeMessage =
  | { type: 'browserbridge.syncNow' }
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

async function syncOnce(): Promise<void> {
  let config = await getConfig();

  if (!config.apiUrl || !config.apiToken) {
    return;
  }

  config = await ensureRegistered(config);

  if (config.sync.tabs) {
    await syncOpenTabs(config);
  }

  if (config.sync.history) {
    await syncHistory(config);
  }

  await getIncomingCommands(config);
  await updateConfig({
    lastSyncAt: new Date().toISOString(),
    lastError: null,
  });
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
      bookmarkSnapshots: [],
      historyItems: [],
    };
  }

  const [devices, incomingCommands, currentTab, bookmarkSnapshots, historyItems] = await Promise.all([
    fetchDevices(config),
    getIncomingCommands(config),
    getCurrentTab(),
    fetchBookmarkSnapshots(config),
    searchHistory(config),
  ]);

  return {
    config,
    configured: true,
    devices,
    incomingCommands,
    currentTab,
    bookmarkSnapshots,
    historyItems,
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
  void syncOnce().catch(captureError);
});

chrome.alarms?.onAlarm.addListener((alarm) => {
  if (alarm.name === SYNC_ALARM_NAME) {
    void syncOnce().catch(captureError);
  }
});

chrome.runtime.onMessage.addListener((message: RuntimeMessage, _sender, sendResponse) => {
  const respond = async (): Promise<unknown> => {
    if (message.type === 'browserbridge.syncNow') {
      await syncOnce();

      return getStatus();
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
