import { uploadHistoryBatch } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import { updateConfig } from './storage';
import type { ExtensionConfig } from './types';

const MAX_HISTORY_BATCH_SIZE = 500;

export async function syncHistory(config: ExtensionConfig, resync = false): Promise<{ count: number; skipped: number }> {
  if (!config.sync.history) {
    return { count: 0, skipped: 0 };
  }

  const adapter = getBrowserAdapter();

  if (!adapter.supportsHistory()) {
    return { count: 0, skipped: 0 };
  }

  let startTime: number;

  if (!resync && config.lastHistorySyncAt) {
    startTime = new Date(config.lastHistorySyncAt).getTime();
  } else {
    const range = config.historySyncRange || '24h';
    switch (range) {
      case '24h': startTime = Date.now() - 24 * 60 * 60 * 1000; break;
      case '7d': startTime = Date.now() - 7 * 24 * 60 * 60 * 1000; break;
      case '30d': startTime = Date.now() - 30 * 24 * 60 * 60 * 1000; break;
      case 'all': startTime = 0; break;
      default: startTime = Date.now() - 24 * 60 * 60 * 1000;
    }
  }

  console.debug(`[BrowserBridge] Fetching history since ${new Date(startTime).toLocaleString()} (Range: ${config.historySyncRange}, Resync: ${resync})`);

  const { items, skipped: frontendSkipped } = await adapter.getHistorySince(startTime);
  const batch = items.slice(0, MAX_HISTORY_BATCH_SIZE);
  
  console.debug(`[BrowserBridge] Found ${items.length} syncable items, uploading batch of ${batch.length}`);

  const response = await uploadHistoryBatch(config, batch);
  await updateConfig({ lastHistorySyncAt: new Date().toISOString() });

  const stored = response?.stored ?? batch.length;
  const backendSkipped = response?.skipped ?? 0;

  console.debug(`[BrowserBridge] History sync complete. Stored: ${stored}, Skipped frontend: ${frontendSkipped}, Skipped backend: ${backendSkipped}`);

  return {
    count: stored,
    skipped: frontendSkipped + backendSkipped,
  };
}
