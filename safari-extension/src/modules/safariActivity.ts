import type { HistoryBatchItem, TabSnapshotItem } from '../../../chrome-extension/src/modules/types';
import { isSyncableUrl } from '../../../chrome-extension/src/modules/urlFilter';

export const SAFARI_ACTIVITY_STORAGE_KEY = 'browserbridge.safariActivity';

export type SafariActivitySource = 'sent_tab' | 'opened_incoming_tab' | 'manual_sync_current_tab';

export type SafariActivityItem = HistoryBatchItem & {
  source: SafariActivitySource;
};

export function activityItemFromTab(tab: TabSnapshotItem | null, source: SafariActivitySource): SafariActivityItem | null {
  if (!tab?.url || !isSyncableUrl(tab.url)) {
    return null;
  }

  return {
    url: tab.url,
    title: tab.title || tab.url,
    visited_at: new Date().toISOString(),
    source,
  };
}

export function activityItemFromUrl(url: string | null | undefined, title: string | null | undefined, source: SafariActivitySource): SafariActivityItem | null {
  if (!url || !isSyncableUrl(url)) {
    return null;
  }

  return {
    url,
    title: title || url,
    visited_at: new Date().toISOString(),
    source,
  };
}

export function uniqueRecentActivity(items: SafariActivityItem[]): SafariActivityItem[] {
  const seen = new Set<string>();

  return items
    .filter((item) => item.url && isSyncableUrl(item.url))
    .sort((left, right) => new Date(right.visited_at).getTime() - new Date(left.visited_at).getTime())
    .filter((item) => {
      const key = `${item.source}:${item.url}:${item.visited_at.slice(0, 16)}`;

      if (seen.has(key)) {
        return false;
      }

      seen.add(key);

      return true;
    })
    .slice(0, 1000);
}
