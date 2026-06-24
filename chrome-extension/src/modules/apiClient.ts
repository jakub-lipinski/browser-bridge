import type {
  ApiCollection,
  ApiItem,
  BookmarkSnapshotItem,
  BookmarkSnapshotResource,
  DeviceResource,
  ExtensionConfig,
  HistoryBatchItem,
  HistoryItemResource,
  TabCommandResource,
  TabSnapshotItem,
} from './types';

type RequestOptions = {
  method?: 'GET' | 'POST';
  body?: unknown;
  query?: Record<string, string | number | boolean | undefined>;
};

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

  return await response.json() as T;
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

export async function uploadBookmarkSnapshot(config: ExtensionConfig, items: BookmarkSnapshotItem[]): Promise<void> {
  await request(config, '/api/bookmarks/snapshot', {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
      items,
    },
  });
}

export async function uploadTabSnapshot(config: ExtensionConfig, tabs: TabSnapshotItem[]): Promise<void> {
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

  return response.data;
}

export async function fetchIncomingTabCommands(config: ExtensionConfig): Promise<TabCommandResource[]> {
  const response = await request<ApiCollection<TabCommandResource>>(config, '/api/tabs/incoming', {
    query: {
      device_uuid: config.deviceUuid,
    },
  });

  return response.data;
}

export async function searchHistory(config: ExtensionConfig, query = ''): Promise<HistoryItemResource[]> {
  const response = await request<ApiCollection<HistoryItemResource>>(config, '/api/history/search', {
    query: {
      device_uuid: config.deviceUuid,
      query,
      limit: 50,
    },
  });

  return response.data;
}

export async function markTabCommandOpened(config: ExtensionConfig, commandId: number): Promise<TabCommandResource> {
  const response = await request<ApiItem<TabCommandResource>>(config, `/api/tabs/${commandId}/opened`, {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
    },
  });

  return response.data;
}

export async function markTabCommandDismissed(config: ExtensionConfig, commandId: number): Promise<TabCommandResource> {
  const response = await request<ApiItem<TabCommandResource>>(config, `/api/tabs/${commandId}/dismissed`, {
    method: 'POST',
    body: {
      device_uuid: config.deviceUuid,
    },
  });

  return response.data;
}
