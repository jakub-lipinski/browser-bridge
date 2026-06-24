import { uploadHistoryBatch } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import { updateConfig } from './storage';
import type { ExtensionConfig } from './types';

const DEFAULT_HISTORY_LOOKBACK_MS = 24 * 60 * 60 * 1000;
const MAX_HISTORY_BATCH_SIZE = 500;

export async function syncHistory(config: ExtensionConfig): Promise<number> {
  if (!config.sync.history) {
    return 0;
  }

  const adapter = getBrowserAdapter();

  if (!adapter.supportsHistory()) {
    return 0;
  }

  const startTime = config.lastHistorySyncAt
    ? new Date(config.lastHistorySyncAt).getTime()
    : Date.now() - DEFAULT_HISTORY_LOOKBACK_MS;

  const batch = (await adapter.getHistorySince(startTime)).slice(0, MAX_HISTORY_BATCH_SIZE);

  await uploadHistoryBatch(config, batch);
  await updateConfig({ lastHistorySyncAt: new Date().toISOString() });

  return batch.length;
}
