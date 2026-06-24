import { uploadTabSnapshot } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import type { ExtensionConfig, TabSnapshotItem } from './types';

export async function getCurrentTab(): Promise<TabSnapshotItem | null> {
  return getBrowserAdapter().getCurrentTab();
}

export async function syncOpenTabs(config: ExtensionConfig): Promise<number> {
  const syncableTabs = await getBrowserAdapter().getAllTabs();

  await uploadTabSnapshot(config, syncableTabs);

  return syncableTabs.length;
}
