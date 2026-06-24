import type {
  ApiCollection,
  ApiItem,
  BookmarkSnapshotItem,
  BookmarkSnapshotResource,
  DeviceResource,
  ExtensionConfig,
  HistoryBatchItem,
  HistoryItemResource,
  NormalizedBookmarkResource,
  TabCommandResource,
  TabSnapshotItem,
} from './types';

type RequestOptions = {
  method?: 'DELETE' | 'GET' | 'POST';
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined>;
};

const DEBUG = false;

export function normalizeArrayResponse<T>(response: unknown, possibleKeys = ['data', 'devices', 'commands', 'snapshots', 'bookmarks', 'history', 'items']): T[] {
  if (!response) return [];
  if (Array.isArray(response)) return response;
  if (typeof response !== 'object') return [];

  for (const key of possibleKeys) {
    if (key in response && Array.isArray((response as any)[key])) {
      return (response as any)[key];
    }
  }

  if (DEBUG) {
    console.warn('[BrowserBridge] Could not extract array from response:', response);
  }

  return [];
}

export function unwrapData<T>(response: unknown): T {
  if (response && typeof response === 'object' && 'data' in response) {
    return (response as any).data;
  }
  return response as T;
}

function normalizeApiUrl(apiUrl: string): string {
  return apiUrl.replace(/\/+$/, '');
}

function makeUrl(config: ExtensionConfig, path: string, query: RequestOptions['query'] = {}): string {
  const url = new URL(`${normalizeApiUrl(config.apiUrl)}${path}`);

  Object.entries(query).forEach(([key, value]) => {
    if (value !== undefined) {
      url.searchParams.set(key, String(value));
    }
  });

  return url.toString();
}

async function request<T>(config: ExtensionConfig, path: string, options: RequestOptions = {}): Promise<T> {
  const response = await fetch(makeUrl(config, path, options.query), {
    method: options.method ?? 'GET',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${config.apiToken}`,
      ...(options.body === undefined ? {} : { 'Content-Type': 'application/json' }),
    },
    body: options.body === undefined ? undefined : JSON.stringify(options.body),
  });

  if (!response.ok) {
    const fallbackMessage = `BrowserBridge API request failed with status ${response.status}.`;
    const errorPayload = await response.json().catch(() => ({ message: fallbackMessage })) as { message?: string };

    throw new Error(errorPayload.message || fallbackMessage);
  }

  const responseJson = await response.json();

  if (DEBUG) {
    console.log(`[BrowserBridge] API response for ${path}:`, responseJson);
  }

  return responseJson as T;
}

export async function registerDevice(config: ExtensionConfig): Promise<DeviceResource> {
  const response = await request<ApiItem<DeviceResource>>(config, '/api/device/register', {
    method: 'POST',
    body: {
      ...(config.deviceUuid ? { device_uuid: config.deviceUuid } : {}),
      name: config.deviceName,
      browser: config.browserName || 'Chrome',
      platform: config.platform,
    },
  });

  return response.data;
}

export async function fetchDevices(config: ExtensionConfig): Promise<DeviceResource[]> {
  const response = await request<ApiCollection<DeviceResource>>(config, '/api/devices', {
    query: {
      device_uuid: config.deviceUuid,
    },
  });

  return response.data;
}

export async function fetchBookmarkSnapshots(config: ExtensionConfig): Promise<BookmarkSnapshotResource[]> {
  const response = await request<ApiCollection<BookmarkSnapshotResource>>(config, '/api/bookmarks/snapshots', {
    query: {
      device_uuid: config.deviceUuid,
    },
  });

  return response.data;
}

export async function fetchBookmarks(config: ExtensionConfig): Promise<NormalizedBookmarkResource[]> {
  const response = await request<ApiCollection<NormalizedBookmarkResource>>(config, '/api/bookmarks', {
    query: {
      device_uuid: config.deviceUuid,
      limit: 100,
    },
  });

  return response.data;
}

export async function searchBookmarks(config: ExtensionConfig, query = ''): Promise<NormalizedBookmarkResource[]> {
  const response = await request<ApiCollection<NormalizedBookmarkResource>>(config, '/api/bookmarks/search', {
    query: {
      device_uuid: config.deviceUuid,
      q: query,
      limit: 100,
    },
  });

  return response.data;
}

export async function uploadBookmarkSnapshot(config: ExtensionConfig, items: BookmarkSnapshotItem[]): Promise<void> {
  if (DEBUG) {
    console.log(`[BrowserBridge] Uploading bookmark snapshot with ${items.length} items.`);
  }

  await request(config, '/api/bookmarks/snapshot', {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
      items,
    },
  });
}

export async function uploadTabSnapshot(config: ExtensionConfig, tabs: TabSnapshotItem[]): Promise<void> {
  if (DEBUG) {
    console.log(`[BrowserBridge] Uploading tab snapshot with ${tabs.length} tabs.`);
  }

  await request(config, '/api/tabs/snapshot', {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
      tabs,
    },
  });
}

export async function uploadHistoryBatch(config: ExtensionConfig, items: HistoryBatchItem[]): Promise<void> {
  if (items.length === 0) {
    return;
  }

  if (DEBUG) {
    console.log(`[BrowserBridge] Uploading history batch with ${items.length} items.`);
  }

  await request(config, '/api/history/batch', {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
      history_sync_enabled: true,
      items,
    },
  });
}

export async function sendTabCommand(
  config: ExtensionConfig,
  targetDeviceUuid: string,
  tab: { url: string; title?: string },
): Promise<TabCommandResource> {
  const response = await request<ApiItem<TabCommandResource>>(config, '/api/tabs/send', {
    method: 'POST',
    body: {
      source_device_uuid: config.deviceUuid,
      target_device_uuid: targetDeviceUuid,
      url: tab.url,
      title: tab.title || null,
    },
  });

  return unwrapData(response);
}

export async function fetchIncomingTabCommands(config: ExtensionConfig): Promise<TabCommandResource[]> {
  const response = await request<ApiCollection<TabCommandResource>>(config, '/api/tabs/incoming', {
    query: {
      device_uuid: config.deviceUuid,
    },
  });

  return normalizeArrayResponse(response);
}

export async function searchHistory(config: ExtensionConfig, query = ''): Promise<HistoryItemResource[]> {
  const response = await request<ApiCollection<HistoryItemResource>>(config, '/api/history/search', {
    query: {
      device_uuid: config.deviceUuid,
      q: query,
      limit: 50,
    },
  });

  return normalizeArrayResponse(response);
}

export async function deleteSyncedHistory(config: ExtensionConfig): Promise<void> {
  await request(config, '/api/history', {
    method: 'DELETE',
    body: {
      device_uuid: config.deviceUuid,
    },
  });
}

export async function markTabCommandOpened(config: ExtensionConfig, commandId: number): Promise<TabCommandResource> {
  const response = await request<ApiItem<TabCommandResource>>(config, `/api/tabs/${commandId}/opened`, {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
    },
  });

  return unwrapData(response);
}

export async function markTabCommandDismissed(config: ExtensionConfig, commandId: number): Promise<TabCommandResource> {
  const response = await request<ApiItem<TabCommandResource>>(config, `/api/tabs/${commandId}/dismissed`, {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
    },
  });

  return unwrapData(response);
}
