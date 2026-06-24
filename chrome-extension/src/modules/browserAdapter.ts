import type { BookmarkSnapshotItem, HistoryBatchItem, TabSnapshotItem } from './types';

export type BrowserAdapter = {
  getBrowserName(): 'chrome' | 'safari';
  getPlatform(): 'windows' | 'macos' | 'ios';
  getCurrentTab(): Promise<TabSnapshotItem | null>;
  openTab(url: string): Promise<void>;
  getAllTabs(): Promise<TabSnapshotItem[]>;
  getBookmarksTree(): Promise<BookmarkSnapshotItem[]>;
  getHistorySince(timestamp: number): Promise<HistoryBatchItem[]>;
  getStorage<T>(key: string): Promise<T | undefined>;
  setStorage<T>(key: string, value: T): Promise<void>;
  supportsBookmarks(): boolean;
  supportsHistory(): boolean;
  supportsNativeBookmarkWrite(): boolean;
  supportsReliableBackgroundSync(): boolean;
};

let activeAdapter: BrowserAdapter | null = null;

export function setBrowserAdapter(adapter: BrowserAdapter): void {
  activeAdapter = adapter;
}

export function getBrowserAdapter(): BrowserAdapter {
  if (!activeAdapter) {
    throw new Error('BrowserBridge browser adapter has not been initialized.');
  }

  return activeAdapter;
}
