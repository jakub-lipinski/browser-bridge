import type { ExtensionConfig } from './types';
import { getBrowserAdapter } from './browserAdapter';

const STORAGE_KEY = 'browserbridge.config';

export const defaultConfig: ExtensionConfig = {
  apiUrl: '',
  apiToken: '',
  deviceUuid: '',
  deviceName: '',
  browserName: 'Chrome',
  platform: 'unknown',
  sync: {
    bookmarks: true,
    tabs: true,
    history: false,
  },
  syncHistory: false,
  lastSyncAt: null,
  lastBookmarkSyncAt: null,
  lastHistorySyncAt: null,
  historyConsentConfirmedAt: null,
  lastError: null,
};

export async function getConfig(): Promise<ExtensionConfig> {
  const config = await getBrowserAdapter().getStorage<Partial<ExtensionConfig>>(STORAGE_KEY);

  return {
    ...defaultConfig,
    ...config,
    syncHistory: config?.syncHistory ?? config?.sync?.history ?? defaultConfig.syncHistory,
    sync: {
      ...defaultConfig.sync,
      ...config?.sync,
      history: config?.syncHistory ?? config?.sync?.history ?? defaultConfig.sync.history,
    },
  };
}

export async function saveConfig(config: ExtensionConfig): Promise<void> {
  await getBrowserAdapter().setStorage(STORAGE_KEY, config);
}

export async function updateConfig(patch: Partial<ExtensionConfig>): Promise<ExtensionConfig> {
  const config = await getConfig();
  const nextConfig: ExtensionConfig = {
    ...config,
    ...patch,
    syncHistory: patch.syncHistory ?? patch.sync?.history ?? config.syncHistory,
    sync: {
      ...config.sync,
      ...patch.sync,
      ...(patch.syncHistory === undefined ? {} : { history: patch.syncHistory }),
    },
  };

  await saveConfig(nextConfig);

  return nextConfig;
}

export function isConfigured(config: ExtensionConfig): boolean {
  return Boolean(config.apiUrl && config.apiToken && config.deviceUuid);
}
