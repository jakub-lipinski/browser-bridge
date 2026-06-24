import { uploadTabSnapshot } from './apiClient';
import type { ExtensionConfig, TabSnapshotItem } from './types';
import { isSyncableUrl } from './urlFilter';

export async function getCurrentTab(): Promise<TabSnapshotItem | null> {
  const [tab] = await chrome.tabs.query({ active: true, currentWindow: true });

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

export async function syncOpenTabs(config: ExtensionConfig): Promise<number> {
  const tabs = await chrome.tabs.query({});
  const syncableTabs = tabs
    .filter((tab) => !tab.incognito && isSyncableUrl(tab.url))
    .map((tab): TabSnapshotItem => ({
      id: tab.id,
      title: tab.title,
      url: tab.url as string,
      active: tab.active,
      windowId: tab.windowId,
    }));

  await uploadTabSnapshot(config, syncableTabs);

  return syncableTabs.length;
}
