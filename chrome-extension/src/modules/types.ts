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
  syncHistory: boolean;
  lastSyncAt: string | null;
  lastBookmarkSyncAt: string | null;
  lastHistorySyncAt: string | null;
  historyConsentConfirmedAt: string | null;
  lastError: string | null;
};

export type DeviceResource = {
  id: number;
  uuid: string;
  name: string;
  browser: string;
  platform: string;
  capabilities?: Record<string, boolean>;
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
  external_id?: string;
  parent_external_id?: string;
  type: 'folder' | 'bookmark';
  title?: string;
  url?: string | null;
  path?: string[];
  date_added?: string | null;
};

export type BookmarkSnapshotResource = {
  id: number;
  device_id: number;
  device?: DeviceResource;
  item_count: number;
  payload_json: {
    items?: BookmarkSnapshotItem[];
  } | null;
  created_at: string | null;
};

export type NormalizedBookmarkResource = {
  id: number;
  device_id: number;
  device?: DeviceResource;
  external_id: string | null;
  parent_external_id: string | null;
  type: 'folder' | 'bookmark';
  title: string | null;
  url: string | null;
  path: string[];
  date_added: string | null;
  created_at: string | null;
  updated_at: string | null;
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

export type HistoryItemResource = HistoryBatchItem & {
  id: number;
  device_id: number;
  device?: DeviceResource;
  created_at?: string | null;
};

export type ApiCollection<T> = {
  data: T[];
};

export type ApiItem<T> = {
  data: T;
};

export type SyncCategoryResult = {
  success: boolean;
  count: number;
  error?: string;
};

export type SyncSummary = {
  bookmarks?: SyncCategoryResult;
  tabs?: SyncCategoryResult;
  history?: SyncCategoryResult;
};
