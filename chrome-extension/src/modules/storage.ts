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
  lastSyncAt: null,
  lastHistorySyncAt: null,
  lastError: null,
};

export async function getConfig(): Promise<ExtensionConfig> {
  const config = await getBrowserAdapter().getStorage<Partial<ExtensionConfig>>(STORAGE_KEY);

  return {
    ...defaultConfig,
    ...config,
    sync: {
      ...defaultConfig.sync,
      ...config?.sync,
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
    sync: {
      ...config.sync,
      ...patch.sync,
    },
  };

  await saveConfig(nextConfig);

  return nextConfig;
}

export function isConfigured(config: ExtensionConfig): boolean {
  return Boolean(config.apiUrl && config.apiToken && config.deviceUuid);
}
