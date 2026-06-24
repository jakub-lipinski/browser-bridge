export type SyncToggles = {
  bookmarks: boolean;
  tabs: boolean;
  history: boolean;
};

export type ExtensionConfig = {
  apiUrl: string;
  apiToken: string;
  deviceUuid: string;
  deviceName: string;
  browserName: string;
  platform: string;
  sync: SyncToggles;
  lastSyncAt: string | null;
  lastHistorySyncAt: string | null;
  lastError: string | null;
};

export type DeviceResource = {
  id: number;
  uuid: string;
  name: string;
  browser: string;
  platform: string;
  last_seen_at: string | null;
};

export type TabCommandResource = {
  id: number;
  source_device_id: number;
  target_device_id: number;
  source_device?: DeviceResource;
  url: string | null;
  title: string | null;
  status: 'pending' | 'opened' | 'dismissed';
  created_at: string | null;
};

export type BookmarkSnapshotItem = {
  id?: string;
  parentId?: string;
  title?: string;
  url: string;
};

export type TabSnapshotItem = {
  id?: number;
  title?: string;
  url: string;
  active?: boolean;
  windowId?: number;
};

export type HistoryBatchItem = {
  url: string;
  title?: string;
  visited_at: string;
};

export type ApiCollection<T> = {
  data: T[];
};

export type ApiItem<T> = {
  data: T;
};
