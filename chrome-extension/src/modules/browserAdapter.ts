import type { BookmarkSnapshotItem, HistoryBatchItem, TabSnapshotItem } from './types';

export type HistoryBatchResult = {
  items: HistoryBatchItem[];
  skipped: number;
  skippedReasons: Record<string, number>;
};

export type BrowserAdapter = {
  getBrowserName(): 'chrome' | 'safari';
  getPlatform(): 'windows' | 'macos' | 'ios';
  getCurrentTab(): Promise<TabSnapshotItem | null>;
  openTab(url: string): Promise<void>;
  getAllTabs(): Promise<TabSnapshotItem[]>;
  getBookmarksTree(): Promise<BookmarkSnapshotItem[]>;
  getHistorySince(timestamp: number): Promise<HistoryBatchResult>;
  getNativeBookmarkTree(): Promise<chrome.bookmarks.BookmarkTreeNode[]>;
  findNativeFolderByPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode | null>;
  findOrCreateNativeFolderPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode>;
  createNativeFolder(parentId: string, title: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode>;
  createNativeBookmark(parentId: string, title: string, url: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode>;
  updateNativeBookmark(id: string, changes: { title?: string; url?: string }): Promise<chrome.bookmarks.BookmarkTreeNode>;
  moveNativeBookmark(id: string, destination: { parentId: string; index?: number }): Promise<chrome.bookmarks.BookmarkTreeNode>;
  removeNativeBookmarkTree(id: string): Promise<void>;
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
