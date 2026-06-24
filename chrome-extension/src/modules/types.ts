export type SyncToggles = {
  bookmarks: boolean;
  tabs: boolean;
  history: boolean;
};

export type HistorySyncRange = '24h' | '7d' | '30d' | 'all';

export type ExtensionConfig = {
  apiUrl: string;
  apiToken: string;
  deviceUuid: string;
  deviceName: string;
  browserName: string;
  platform: string;
  sync: SyncToggles;
  syncHistory: boolean;
  historySyncRange: HistorySyncRange;
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

export type BookmarkSyncMode = 'safe_folder' | 'merge' | 'mirror';

export type BookmarkSyncDirection = 'source_to_target' | 'target_to_source' | 'two_way';

export type BookmarkSyncTargetScope = 'browserbridge_folder' | 'selected_folder' | 'entire_bookmarks_root';

export type BookmarkSyncRunStatus = 'preview' | 'running' | 'completed' | 'failed' | 'cancelled';

export type BookmarkSyncProfileResource = {
  id: number;
  name: string;
  source_device_id: number;
  source_device?: DeviceResource;
  target_device_id: number;
  target_device?: DeviceResource;
  mode: BookmarkSyncMode;
  direction: BookmarkSyncDirection;
  target_scope: BookmarkSyncTargetScope;
  selected_target_folder_id: string | null;
  auto_sync_enabled: boolean;
  auto_sync_interval_minutes: number | null;
  last_run_at: string | null;
  next_run_at: string | null;
  is_active: boolean;
  latest_run?: BookmarkSyncRunResource;
  created_at: string | null;
  updated_at: string | null;
};

export type BookmarkSyncProfilePayload = {
  name: string;
  source_device_id: number;
  target_device_id: number;
  mode: BookmarkSyncMode;
  direction: BookmarkSyncDirection;
  target_scope: BookmarkSyncTargetScope;
  selected_target_folder_id?: string | null;
  auto_sync_enabled?: boolean;
  auto_sync_interval_minutes?: number | null;
  is_active?: boolean;
};

export type BookmarkSyncPreviewChange = {
  action: 'add' | 'update' | 'move' | 'delete' | 'skip';
  title?: string | null;
  url?: string | null;
  path?: string[];
};

export type BookmarkSyncPreviewData = {
  mode: BookmarkSyncMode;
  source_device: string | null;
  target_device: string | null;
  add_count: number;
  update_count: number;
  move_count: number;
  delete_count: number;
  skip_count: number;
  duplicate_count: number;
  invalid_count: number;
  warnings: string[];
  sample_changes: BookmarkSyncPreviewChange[];
  run_id?: number;
};

export type BookmarkSyncRunResource = {
  id: number;
  profile_id: number;
  profile?: BookmarkSyncProfileResource;
  source_device_id: number;
  source_device?: DeviceResource;
  target_device_id: number;
  target_device?: DeviceResource;
  mode: BookmarkSyncMode;
  status: BookmarkSyncRunStatus;
  added_count: number;
  updated_count: number;
  moved_count: number;
  deleted_count: number;
  skipped_count: number;
  duplicate_count: number;
  invalid_count: number;
  error_message: string | null;
  preview: BookmarkSyncPreviewData | null;
  result: Record<string, unknown> | null;
  created_at: string | null;
  updated_at: string | null;
};

export type BookmarkBackupResource = {
  id: number;
  device_id: number;
  device?: DeviceResource;
  sync_run_id: number | null;
  has_payload: boolean;
  has_encrypted_payload: boolean;
  created_at: string | null;
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

export type TabSnapshotResource = {
  id: number;
  device_id: number;
  device?: DeviceResource;
  tab_count: number;
  payload_json: {
    tabs?: TabSnapshotItem[];
  } | null;
  created_at: string | null;
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
  skipped?: number;
  error?: string;
};

export type SyncSummary = {
  bookmarks?: SyncCategoryResult;
  tabs?: SyncCategoryResult;
  history?: SyncCategoryResult;
};
