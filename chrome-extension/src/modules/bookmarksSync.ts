import { uploadBookmarkSnapshot } from './apiClient';
import { getBrowserAdapter } from './browserAdapter';
import type { ExtensionConfig } from './types';

export async function syncBookmarks(config: ExtensionConfig): Promise<number> {
  const adapter = getBrowserAdapter();

  if (!adapter.supportsBookmarks()) {
    return 0;
  }

  const items = await adapter.getBookmarksTree();

  await uploadBookmarkSnapshot(config, items);

  return items.length;
}
