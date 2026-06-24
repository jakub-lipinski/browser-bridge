import type { BrowserAdapter } from './browserAdapter';
import type { BookmarkSnapshotItem, HistoryBatchItem, TabSnapshotItem } from './types';
import { isSyncableUrl } from './urlFilter';

export class ChromiumBrowserAdapter implements BrowserAdapter {
  getBrowserName(): 'chrome' {
    return 'chrome';
  }

  getPlatform(): 'windows' | 'macos' | 'ios' {
    const platform = navigator.platform || navigator.userAgent;
    const normalizedPlatform = platform.toLowerCase();

    if (normalizedPlatform.includes('win')) {
      return 'windows';
    }

    if (normalizedPlatform.includes('iphone') || normalizedPlatform.includes('ipad')) {
      return 'ios';
    }

    return 'macos';
  }

  async getCurrentTab(): Promise<TabSnapshotItem | null> {
    const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

    return this.mapTab(tab);
  }

  async openTab(url: string): Promise<void> {
    await chrome.tabs.create({ url });
  }

  async getAllTabs(): Promise<TabSnapshotItem[]> {
    const tabs = await chrome.tabs.query({});

    return tabs
      .filter((tab) => !tab.incognito)
      .map((tab) => this.mapTab(tab))
      .filter((tab): tab is TabSnapshotItem => tab !== null);
  }

  async getBookmarksTree(): Promise<BookmarkSnapshotItem[]> {
    const tree = await chrome.bookmarks.getTree();
    const items: BookmarkSnapshotItem[] = [];

    const visit = (node: chrome.bookmarks.BookmarkTreeNode, path: string[] = []): void => {
      const nextPath = node.title ? [...path, node.title] : path;

      if (node.url) {
        if (!isSyncableUrl(node.url)) {
          return;
        }

        items.push({
          external_id: node.id,
          parent_external_id: node.parentId,
          type: 'bookmark',
          title: node.title,
          url: node.url,
          path,
          date_added: node.dateAdded ? new Date(node.dateAdded).toISOString() : null,
        });
      } else if (node.id !== '0') {
        items.push({
          external_id: node.id,
          parent_external_id: node.parentId,
          type: 'folder',
          title: node.title,
          url: null,
          path: nextPath,
          date_added: node.dateAdded ? new Date(node.dateAdded).toISOString() : null,
        });
      }

      node.children?.forEach((child) => visit(child, nextPath));
    };

    tree.forEach(visit);

    return items;
  }

  async getHistorySince(timestamp: number): Promise<HistoryBatchItem[]> {
    const historyItems = await chrome.history.search({
      text: '',
      startTime: timestamp,
      maxResults: 500,
    });

    return historyItems
      .filter((item) => isSyncableUrl(item.url))
      .map((item) => ({
        url: item.url as string,
        title: item.title || undefined,
        visited_at: new Date(item.lastVisitTime || Date.now()).toISOString(),
      }));
  }

  async getStorage<T>(key: string): Promise<T | undefined> {
    const stored = await chrome.storage.local.get(key);

    return stored[key] as T | undefined;
  }

  async setStorage<T>(key: string, value: T): Promise<void> {
    await chrome.storage.local.set({ [key]: value });
  }

  supportsBookmarks(): boolean {
    return true;
  }

  supportsHistory(): boolean {
    return true;
  }

  supportsNativeBookmarkWrite(): boolean {
    return false;
  }

  supportsReliableBackgroundSync(): boolean {
    return true;
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
