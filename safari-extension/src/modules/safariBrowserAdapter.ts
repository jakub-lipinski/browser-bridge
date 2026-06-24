import type {
  BrowserAdapter,
  BrowserCapabilityAudit,
  BrowserCapabilityProbe,
  HistoryBatchResult,
} from '../../../chrome-extension/src/modules/browserAdapter';
import type {
  BookmarkSnapshotItem,
  TabSnapshotItem,
} from '../../../chrome-extension/src/modules/types';
import { isSyncableUrl } from '../../../chrome-extension/src/modules/urlFilter';
import { SAFARI_ACTIVITY_STORAGE_KEY, type SafariActivityItem } from './safariActivity';

type WebExtensionApi = typeof chrome & {
  bookmarks?: typeof chrome.bookmarks;
  history?: typeof chrome.history;
  tabs?: typeof chrome.tabs;
  alarms?: typeof chrome.alarms;
  storage?: typeof chrome.storage;
};

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
    const tabsApi = this.tabsApi();

    if (!tabsApi?.query) {
      return null;
    }

    try {
      const tabs = await this.invokeApi<chrome.tabs.Tab[]>(tabsApi, 'query', [{ active: true, currentWindow: true }]);

      return this.mapTab(tabs[0]);
    } catch {
      return null;
    }
  }

  async openTab(url: string): Promise<void> {
    if (!isSyncableUrl(url)) {
      throw new Error('Safari can only open http and https BrowserBridge tab commands.');
    }

    const tabsApi = this.tabsApi();

    if (!tabsApi?.create) {
      throw new Error('Safari tab opening is not available in this Safari version.');
    }

    try {
      await this.invokeApi<chrome.tabs.Tab>(tabsApi, 'create', [{ url }]);
    } catch {
      throw new Error('Safari could not open this tab. Check that the Safari extension is enabled.');
    }
  }

  async getAllTabs(): Promise<TabSnapshotItem[]> {
    const tabsApi = this.tabsApi();

    if (!tabsApi?.query) {
      return [];
    }

    try {
      const tabs = await this.invokeApi<chrome.tabs.Tab[]>(tabsApi, 'query', [{}]);

      return tabs
        .filter((tab) => !tab.incognito)
        .map((tab) => this.mapTab(tab))
        .filter((tab): tab is TabSnapshotItem => tab !== null);
    } catch {
      return [];
    }
  }

  async getBookmarksTree(): Promise<BookmarkSnapshotItem[]> {
    const tree = await this.getNativeBookmarkTree();
    const items: BookmarkSnapshotItem[] = [];

    const visit = (node: chrome.bookmarks.BookmarkTreeNode, path: string[] = []): void => {
      const safeTitle = typeof node.title === 'string' && node.title.trim() !== ''
        ? node.title
        : (node.url ? 'Untitled bookmark' : 'Untitled folder');
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

      node.children?.forEach((child) => visit(child, nextPath));
    };

    tree.forEach((node) => visit(node, []));

    return items;
  }

  async getNativeBookmarkTree(): Promise<chrome.bookmarks.BookmarkTreeNode[]> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.getTree) {
      throw new Error('Native Safari bookmark reading is not available in this Safari version.');
    }

    return this.invokeApi<chrome.bookmarks.BookmarkTreeNode[]>(bookmarksApi, 'getTree', []);
  }

  async findNativeFolderByPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode | null> {
    const cleanPath = path.map((segment) => segment.trim()).filter(Boolean);
    const tree = await this.getNativeBookmarkTree();
    const root = tree[0];

    if (!root?.children || cleanPath.length === 0) {
      return null;
    }

    return this.findFolderPathFromChildren(root.children, cleanPath);
  }

  async findOrCreateNativeFolderPath(path: string[]): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const cleanPath = path.map((segment) => segment.trim()).filter(Boolean);
    const tree = await this.getNativeBookmarkTree();
    const root = tree[0];

    if (!root?.children?.length || cleanPath.length === 0) {
      throw new Error('Safari bookmark roots are unavailable.');
    }

    let parentId = root.children[0].id;
    let parentChildren = root.children;
    let current: chrome.bookmarks.BookmarkTreeNode | null = null;

    for (const segment of cleanPath) {
      current = this.findDirectFolder(parentChildren, segment);

      if (!current) {
        current = await this.createNativeFolder(parentId, segment);
      }

      parentId = current.id;
      parentChildren = current.children || [];
    }

    return current;
  }

  async createNativeFolder(parentId: string, title: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.create) {
      throw new Error('Native Safari bookmark writing is not available in this Safari version.');
    }

    return this.invokeApi<chrome.bookmarks.BookmarkTreeNode>(bookmarksApi, 'create', [
      {
        parentId,
        title,
        ...(typeof index === 'number' ? { index } : {}),
      },
    ]);
  }

  async createNativeBookmark(parentId: string, title: string, url: string, index?: number): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.create) {
      throw new Error('Native Safari bookmark writing is not available in this Safari version.');
    }

    return this.invokeApi<chrome.bookmarks.BookmarkTreeNode>(bookmarksApi, 'create', [
      {
        parentId,
        title: title || url,
        url,
        ...(typeof index === 'number' ? { index } : {}),
      },
    ]);
  }

  async updateNativeBookmark(id: string, changes: { title?: string; url?: string }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.update) {
      throw new Error('Native Safari bookmark writing is not available in this Safari version.');
    }

    return this.invokeApi<chrome.bookmarks.BookmarkTreeNode>(bookmarksApi, 'update', [id, changes]);
  }

  async moveNativeBookmark(id: string, destination: { parentId: string; index?: number }): Promise<chrome.bookmarks.BookmarkTreeNode> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.move) {
      throw new Error('Native Safari bookmark writing is not available in this Safari version.');
    }

    return this.invokeApi<chrome.bookmarks.BookmarkTreeNode>(bookmarksApi, 'move', [id, destination]);
  }

  async removeNativeBookmarkTree(id: string): Promise<void> {
    const bookmarksApi = this.bookmarksApi();

    if (!bookmarksApi?.removeTree) {
      throw new Error('Native Safari bookmark writing is not available in this Safari version.');
    }

    await this.invokeApi<void>(bookmarksApi, 'removeTree', [id]);
  }

  async getHistorySince(timestamp: number): Promise<HistoryBatchResult> {
    const historyApi = this.historyApi();

    if (historyApi?.search) {
      try {
        const historyItems = await this.invokeApi<chrome.history.HistoryItem[]>(historyApi, 'search', [{
          text: '',
          startTime: timestamp,
          maxResults: 500,
        }]);

        return this.normalizeHistoryItems(historyItems);
      } catch {
        return this.getActivitySince(timestamp);
      }
    }

    return this.getActivitySince(timestamp);
  }

  async getStorage<T>(key: string): Promise<T | undefined> {
    const stored = await chrome.storage.local.get(key);

    return stored[key] as T | undefined;
  }

  async setStorage<T>(key: string, value: T): Promise<void> {
    await chrome.storage.local.set({ [key]: value });
  }

  supportsBookmarks(): boolean {
    return this.hasFunction(this.bookmarksApi()?.getTree);
  }

  supportsHistory(): boolean {
    return true;
  }

  supportsNativeBookmarkWrite(): boolean {
    const bookmarksApi = this.bookmarksApi();

    return this.hasFunction(bookmarksApi?.create)
      && this.hasFunction(bookmarksApi?.update)
      && this.hasFunction(bookmarksApi?.removeTree);
  }

  supportsReliableBackgroundSync(): boolean {
    return this.hasFunction(this.alarmsApi()?.create);
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

    const canReadNativeBookmarks = await record('canReadNativeBookmarks', this.apiLabel('bookmarks.getTree'), async () => {
      await this.getNativeBookmarkTree();

      return true;
    });
    const canWriteNativeBookmarks = await record('canWriteNativeBookmarks', this.apiLabel('bookmarks.create/update/removeTree'), () => (
      canReadNativeBookmarks && this.supportsNativeBookmarkWrite()
    ));
    const canReadNativeHistory = await record('canReadNativeHistory', this.apiLabel('history.search'), async () => {
      const historyApi = this.historyApi();

      if (!historyApi?.search) {
        throw new Error('history.search is unavailable.');
      }

      await this.invokeApi<chrome.history.HistoryItem[]>(historyApi, 'search', [{
        text: '',
        startTime: Date.now() - 60_000,
        maxResults: 1,
      }]);

      return true;
    });
    const canReadCurrentTab = await record('canReadCurrentTab', this.apiLabel('tabs.query(active)'), async () => {
      const tabsApi = this.tabsApi();

      if (!tabsApi?.query) {
        throw new Error('tabs.query is unavailable.');
      }

      await this.invokeApi<chrome.tabs.Tab[]>(tabsApi, 'query', [{ active: true, currentWindow: true }]);

      return true;
    });
    const canReadAllTabs = await record('canReadAllTabs', this.apiLabel('tabs.query(all)'), async () => {
      const tabsApi = this.tabsApi();

      if (!tabsApi?.query) {
        throw new Error('tabs.query is unavailable.');
      }

      await this.invokeApi<chrome.tabs.Tab[]>(tabsApi, 'query', [{}]);

      return true;
    });
    const canOpenTab = await record('canOpenTab', this.apiLabel('tabs.create'), () => this.hasFunction(this.tabsApi()?.create));
    const canUseBackgroundPolling = await record('canUseBackgroundPolling', this.apiLabel('alarms.get'), async () => {
      const alarmsApi = this.alarmsApi();

      if (!alarmsApi?.get) {
        throw new Error('alarms.get is unavailable.');
      }

      await this.invokeApi<chrome.alarms.Alarm | undefined>(alarmsApi, 'get', ['browserbridge.sync']);

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
      historyMode: canReadNativeHistory ? 'native' : (canReadCurrentTab || canOpenTab ? 'activity' : 'unavailable'),
      probes,
    };
  }

  private async getActivitySince(timestamp: number): Promise<HistoryBatchResult> {
    const activity = await this.getStorage<SafariActivityItem[]>(SAFARI_ACTIVITY_STORAGE_KEY) || [];
    const items = activity
      .filter((item) => new Date(item.visited_at).getTime() >= timestamp)
      .map(({ url, title, visited_at }) => ({ url, title, visited_at }));

    return {
      items,
      skipped: 0,
      skippedReasons: {},
    };
  }

  private normalizeHistoryItems(historyItems: chrome.history.HistoryItem[]): HistoryBatchResult {
    const items = [];
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

      items.push({
        url: item.url,
        title: item.title || undefined,
        visited_at: new Date(item.lastVisitTime || Date.now()).toISOString(),
      });
    }

    return { items, skipped, skippedReasons };
  }

  private extensionApi(): WebExtensionApi {
    return ((globalThis as unknown as { browser?: WebExtensionApi }).browser || chrome) as WebExtensionApi;
  }

  private apiNamespace(): 'browser' | 'chrome' {
    return (globalThis as unknown as { browser?: WebExtensionApi }).browser ? 'browser' : 'chrome';
  }

  private apiLabel(api: string): string {
    return `${this.apiNamespace()}.${api}`;
  }

  private bookmarksApi(): typeof chrome.bookmarks | undefined {
    return this.extensionApi().bookmarks || chrome.bookmarks;
  }

  private historyApi(): typeof chrome.history | undefined {
    return this.extensionApi().history || chrome.history;
  }

  private tabsApi(): typeof chrome.tabs | undefined {
    return this.extensionApi().tabs || chrome.tabs;
  }

  private alarmsApi(): typeof chrome.alarms | undefined {
    return this.extensionApi().alarms || chrome.alarms;
  }

  private async invokeApi<T>(api: Record<string, any>, method: string, args: unknown[]): Promise<T> {
    const callable = api?.[method];

    if (typeof callable !== 'function') {
      throw new Error(`${method} is unavailable.`);
    }

    if (this.apiNamespace() === 'browser') {
      return callable.apply(api, args) as Promise<T>;
    }

    return new Promise<T>((resolve, reject) => {
      try {
        const possiblePromise = callable.apply(api, [
          ...args,
          (result: T) => {
            const lastError = chrome.runtime?.lastError;

            if (lastError) {
              reject(new Error(lastError.message));
              return;
            }

            resolve(result);
          },
        ]);

        if (possiblePromise && typeof possiblePromise.then === 'function') {
          possiblePromise.then(resolve, reject);
        }
      } catch (error) {
        reject(error);
      }
    });
  }

  private hasFunction(value: unknown): boolean {
    return typeof value === 'function';
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
