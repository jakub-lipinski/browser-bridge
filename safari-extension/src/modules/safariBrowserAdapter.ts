import type { BrowserAdapter, HistoryBatchResult } from '../../../chrome-extension/src/modules/browserAdapter';
import type {
  BookmarkSnapshotItem,
  TabSnapshotItem,
} from '../../../chrome-extension/src/modules/types';
import { isSyncableUrl } from '../../../chrome-extension/src/modules/urlFilter';

export class SafariBrowserAdapter implements BrowserAdapter {
  getBrowserName(): 'safari' {
    return 'safari';
  }

  getPlatform(): 'macos' | 'ios' {
    const platform = `${navigator.platform} ${navigator.userAgent}`.toLowerCase();

    if (platform.includes('iphone') || platform.includes('ipad')) {
      return 'ios';
    }

    return 'macos';
  }

  async getCurrentTab(): Promise<TabSnapshotItem | null> {
    try {
      const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

      return this.mapTab(tab);
    } catch {
      return null;
    }
  }

  async openTab(url: string): Promise<void> {
    if (!isSyncableUrl(url)) {
      throw new Error('Safari can only open http and https BrowserBridge tab commands.');
    }

    try {
      await chrome.tabs.create({ url });
    } catch {
      throw new Error('Safari could not open this tab. Check that the Safari extension is enabled.');
    }
  }

  async getAllTabs(): Promise<TabSnapshotItem[]> {
    try {
      const tabs = await chrome.tabs.query({});

      return tabs
        .filter((tab) => !tab.incognito)
        .map((tab) => this.mapTab(tab))
        .filter((tab): tab is TabSnapshotItem => tab !== null);
    } catch {
      return [];
    }
  }

  async getBookmarksTree(): Promise<BookmarkSnapshotItem[]> {
    return [];
  }

  async getNativeBookmarkTree(): Promise<chrome.bookmarks.BookmarkTreeNode[]> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async findNativeFolderByPath(_path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode | null> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async findOrCreateNativeFolderPath(_path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async createNativeFolder(_parentId: string, _title: string, _index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async createNativeBookmark(_parentId: string, _title: string, _url: string, _index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async updateNativeBookmark(_id: string, _changes: { title?: string; url?: string }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async moveNativeBookmark(_id: string, _destination: { parentId: string; index?: number }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async removeNativeBookmarkTree(_id: string): Promise<void> {
    throw new Error('Native Safari bookmark writing is not available in this Safari version.');
  }

  async getHistorySince(_timestamp: number): Promise<HistoryBatchResult> {
    return { items: [], skipped: 0, skippedReasons: {} };
  }

  async getStorage<T>(key: string): Promise<T | undefined> {
    const stored = await chrome.storage.local.get(key);

    return stored[key] as T | undefined;
  }

  async setStorage<T>(key: string, value: T): Promise<void> {
    await chrome.storage.local.set({ [key]: value });
  }

  supportsBookmarks(): boolean {
    return false;
  }

  supportsHistory(): boolean {
    return false;
  }

  supportsNativeBookmarkWrite(): boolean {
    return false;
  }

  supportsReliableBackgroundSync(): boolean {
    return false;
  }

  private mapTab(tab: chrome.tabs.Tab | undefined): TabSnapshotItem | null {
    if (!tab?.url || !isSyncableUrl(tab.url)) {
      return null;
    }

    return {
      id: tab.id,
      title: tab.title,
      url: tab.url,
      active: tab.active,
      windowId: tab.windowId,
    };
  }
}
