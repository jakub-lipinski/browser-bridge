import { uploadHistoryBatch } from './apiClient';
import { updateConfig } from './storage';
import type { ExtensionConfig, HistoryBatchItem } from './types';
import { isSyncableUrl } from './urlFilter';

const DEFAULT_HISTORY_LOOKBACK_MS = 24 * 60 * 60 * 1000;
const MAX_HISTORY_BATCH_SIZE = 500;

export async function syncHistory(config: ExtensionConfig): Promise<number> {
  if (!config.sync.history) {
    return 0;
  }

  const startTime = config.lastHistorySyncAt
    ? new Date(config.lastHistorySyncAt).getTime()
    : Date.now() - DEFAULT_HISTORY_LOOKBACK_MS;

  const historyItems = await chrome.history.search({
    text: '',
    startTime,
    maxResults: MAX_HISTORY_BATCH_SIZE,
  });

  const batch: HistoryBatchItem[] = historyItems
    .filter((item) => isSyncableUrl(item.url))
    .map((item) => ({
      url: item.url as string,
      title: item.title || undefined,
      visited_at: new Date(item.lastVisitTime || Date.now()).toISOString(),
    }));

  await uploadHistoryBatch(config, batch);
  await updateConfig({ lastHistorySyncAt: new Date().toISOString() });

  return batch.length;
}
