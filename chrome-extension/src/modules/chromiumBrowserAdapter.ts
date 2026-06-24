import type { BrowserAdapter, HistoryBatchResult } from './browserAdapter';
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

    if (!Array.isArray(tabs)) {
      return [];
    }

    return tabs
      .filter((tab) => !tab.incognito)
      .map((tab) => this.mapTab(tab))
      .filter((tab): tab is TabSnapshotItem => tab !== null);
  }

  async getBookmarksTree(): Promise<BookmarkSnapshotItem[]> {
    const tree = await chrome.bookmarks.getTree();
    const items: BookmarkSnapshotItem[] = [];

    if (!Array.isArray(tree)) {
      return items;
    }

    const visit = (node: chrome.bookmarks.BookmarkTreeNode, path: string[] = []): void => {
      if (!node) return;

      const safeTitle = typeof node.title === 'string' && node.title.trim() !== '' ? node.title : (node.url ? 'Untitled bookmark' : 'Untitled folder');
      const nextPath = node.id !== '0' ? [...path, safeTitle] : path;

      if (node.url) {
        if (!isSyncableUrl(node.url)) {
          return;
        }

        items.push({
          external_id: node.id,
          parent_external_id: node.parentId,
          type: 'bookmark',
          title: safeTitle,
          url: node.url,
          path,
          date_added: node.dateAdded ? new Date(node.dateAdded).toISOString() : null,
        });
      } else if (node.id !== '0') {
        items.push({
          external_id: node.id,
          parent_external_id: node.parentId,
          type: 'folder',
          title: safeTitle,
          url: null,
          path: nextPath,
          date_added: node.dateAdded ? new Date(node.dateAdded).toISOString() : null,
        });
      }

      if (Array.isArray(node.children)) {
        node.children.forEach((child) => visit(child, nextPath));
      }
    };

    tree.forEach((node) => visit(node, []));

    return items;
  }

  async getHistorySince(timestamp: number): Promise<HistoryBatchResult> {
    const historyItems = await chrome.history.search({
      text: '',
      startTime: timestamp,
      maxResults: 500,
    });

    const items: HistoryBatchItem[] = [];
    let skipped = 0;
    const skippedReasons: Record<string, number> = {};

    for (const item of historyItems) {
      if (!item.url) {
        skipped++;
        skippedReasons['missing_url'] = (skippedReasons['missing_url'] || 0) + 1;
        continue;
      }

      if (item.url.length > 2048) {
        skipped++;
        skippedReasons['url_too_long'] = (skippedReasons['url_too_long'] || 0) + 1;
        continue;
      }

      if (!isSyncableUrl(item.url)) {
        skipped++;
        const reason = item.url.match(/^(chrome|edge|brave|safari-extension|about|file|devtools|view-source|javascript):/i) ? 'internal_url' : 'invalid_url';
        skippedReasons[reason] = (skippedReasons[reason] || 0) + 1;
        continue;
      }

      let safeTitle = item.title || undefined;
      
      if (safeTitle && safeTitle.length > 512) {
        safeTitle = safeTitle.substring(0, 512);
      }

      items.push({
        url: item.url,
        title: safeTitle,
        visited_at: new Date(item.lastVisitTime || Date.now()).toISOString(),
      });
    }

    return { items, skipped, skippedReasons };
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
