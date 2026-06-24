import type { BrowserAdapter, BrowserCapabilityAudit, BrowserCapabilityProbe, HistoryBatchResult } from './browserAdapter';
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

  async getNativeBookmarkTree(): Promise<chrome.bookmarks.BookmarkTreeNode[]> {
    return chrome.bookmarks.getTree();
  }

  async findNativeFolderByPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode | null> {
    const cleanPath = path.map((segment) => segment.trim()).filter(Boolean);

    if (cleanPath.length === 0) {
      return null;
    }

    const tree = await this.getNativeBookmarkTree();
    const root = tree[0];

    if (!root?.children) {
      return null;
    }

    return this.findFolderPathFromChildren(root.children, cleanPath);
  }

  async findOrCreateNativeFolderPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const cleanPath = path.map((segment) => segment.trim()).filter(Boolean);

    if (cleanPath.length === 0) {
      throw new Error('Bookmark folder path cannot be empty.');
    }

    const tree = await this.getNativeBookmarkTree();
    const root = tree[0];

    if (!root?.children?.length) {
      throw new Error('Chrome bookmark roots are unavailable.');
    }

    let parentId = this.defaultWritableRootId(root);
    let parentChildren = root.children;
    let current: chrome.bookmarks.BookmarkTreeNode | null = null;

    for (const segment of cleanPath) {
      current = this.findDirectFolder(parentChildren, segment);

      if (!current) {
        current = await chrome.bookmarks.create({ parentId, title: segment });
      }

      parentId = current.id;
      parentChildren = current.children || [];
    }

    return current;
  }

  async createNativeFolder(parentId: string, title: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    return chrome.bookmarks.create({
      parentId,
      title,
      ...(typeof index === 'number' ? { index } : {}),
    });
  }

  async createNativeBookmark(parentId: string, title: string, url: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    return chrome.bookmarks.create({
      parentId,
      title: title || url,
      url,
      ...(typeof index === 'number' ? { index } : {}),
    });
  }

  async updateNativeBookmark(id: string, changes: { title?: string; url?: string }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    return chrome.bookmarks.update(id, changes);
  }

  async moveNativeBookmark(id: string, destination: { parentId: string; index?: number }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    return chrome.bookmarks.move(id, destination);
  }

  async removeNativeBookmarkTree(id: string): Promise<void> {
    await chrome.bookmarks.removeTree(id);
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
    return true;
  }

  supportsReliableBackgroundSync(): boolean {
    return true;
  }

  async getCapabilityAudit(): Promise<BrowserCapabilityAudit> {
    const probes: BrowserCapabilityProbe[] = [];
    const record = async (name: string, api: string, probe: () => Promise<boolean> | boolean): Promise<boolean> => {
      try {
        const success = await probe();
        probes.push({ name, api, success });

        return success;
      } catch (error) {
        probes.push({
          name,
          api,
          success: false,
          error: error instanceof Error ? error.message : String(error),
        });

        return false;
      }
    };

    const canReadNativeBookmarks = await record('canReadNativeBookmarks', 'chrome.bookmarks.getTree', async () => {
      await chrome.bookmarks.getTree();

      return true;
    });
    const canWriteNativeBookmarks = await record('canWriteNativeBookmarks', 'chrome.bookmarks.create/update/removeTree', () => (
      typeof chrome.bookmarks.create === 'function'
      && typeof chrome.bookmarks.update === 'function'
      && typeof chrome.bookmarks.removeTree === 'function'
    ));
    const canReadNativeHistory = await record('canReadNativeHistory', 'chrome.history.search', async () => {
      await chrome.history.search({ text: '', startTime: Date.now() - 60_000, maxResults: 1 });

      return true;
    });
    const canReadCurrentTab = await record('canReadCurrentTab', 'chrome.tabs.query(active)', async () => {
      await chrome.tabs.query({ active: true, currentWindow: true });

      return true;
    });
    const canReadAllTabs = await record('canReadAllTabs', 'chrome.tabs.query(all)', async () => {
      await chrome.tabs.query({});

      return true;
    });
    const canOpenTab = await record('canOpenTab', 'chrome.tabs.create', () => typeof chrome.tabs.create === 'function');
    const canUseBackgroundPolling = await record('canUseBackgroundPolling', 'chrome.alarms.get', async () => {
      await chrome.alarms.get('browserbridge.sync');

      return true;
    });

    return {
      canReadNativeBookmarks,
      canWriteNativeBookmarks,
      canReadNativeHistory,
      canReadCurrentTab,
      canReadAllTabs,
      canOpenTab,
      canUseBackgroundPolling,
      historyMode: canReadNativeHistory ? 'native' : 'unavailable',
      probes,
    };
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

  private defaultWritableRootId(root: chrome.bookmarks.BookmarkTreeNode): string {
    const bookmarksBar = root.children?.find((child) => child.id === '1') || root.children?.[0];

    if (!bookmarksBar) {
      throw new Error('Chrome bookmark root is unavailable.');
    }

    return bookmarksBar.id;
  }

  private findFolderPathFromChildren(children: chrome.bookmarks.BookmarkTreeNode[], path: string[]): chrome.bookmarks.BookmarkTreeNode | null {
    const [segment, ...rest] = path;
    const folder = this.findDirectFolder(children, segment);

    if (!folder) {
      return null;
    }

    if (rest.length === 0) {
      return folder;
    }

    return this.findFolderPathFromChildren(folder.children || [], rest);
  }

  private findDirectFolder(children: chrome.bookmarks.BookmarkTreeNode[], title: string): chrome.bookmarks.BookmarkTreeNode | null {
    return children.find((child) => !child.url && child.title === title) || null;
  }
}
