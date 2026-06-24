import { uploadBookmarkSnapshot } from './apiClient';
import type { BookmarkSnapshotItem, ExtensionConfig } from './types';
import { isSyncableUrl } from './urlFilter';

function flattenBookmarks(nodes: chrome.bookmarks.BookmarkTreeNode[]): BookmarkSnapshotItem[] {
  const items: BookmarkSnapshotItem[] = [];

  const visit = (node: chrome.bookmarks.BookmarkTreeNode): void => {
    if (node.url && isSyncableUrl(node.url)) {
      items.push({
        id: node.id,
        parentId: node.parentId,
        title: node.title,
        url: node.url,
      });
    }

    node.children?.forEach(visit);
  };

  nodes.forEach(visit);

  return items;
}

export async function syncBookmarks(config: ExtensionConfig): Promise<number> {
  const tree = await chrome.bookmarks.getTree();
  const items = flattenBookmarks(tree);

  await uploadBookmarkSnapshot(config, items);

  return items.length;
}
