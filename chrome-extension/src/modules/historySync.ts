import { uploadHistoryBatch } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import { updateConfig } from './storage';
import type { ExtensionConfig } from './types';

const DEFAULT_HISTORY_LOOKBACK_MS = 24 * 60 * 60 * 1000;
const MAX_HISTORY_BATCH_SIZE = 500;

export async function syncHistory(config: ExtensionConfig): Promise<{ count: number; skipped: number }> {
  if (!config.sync.history) {
    return { count: 0, skipped: 0 };
  }

  const adapter = getBrowserAdapter();

  if (!adapter.supportsHistory()) {
    return { count: 0, skipped: 0 };
  }

  const startTime = config.lastHistorySyncAt
    ? new Date(config.lastHistorySyncAt).getTime()
    : Date.now() - DEFAULT_HISTORY_LOOKBACK_MS;

  const { items, skipped: frontendSkipped } = await adapter.getHistorySince(startTime);
  const batch = items.slice(0, MAX_HISTORY_BATCH_SIZE);

  const response = await uploadHistoryBatch(config, batch);
  await updateConfig({ lastHistorySyncAt: new Date().toISOString() });

  const stored = response?.stored ?? batch.length;
  const backendSkipped = response?.skipped ?? 0;

  return {
    count: stored,
    skipped: frontendSkipped + backendSkipped,
  };
}
