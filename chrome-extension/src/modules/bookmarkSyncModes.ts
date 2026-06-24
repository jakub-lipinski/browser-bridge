import { getBrowserAdapter, type BrowserAdapter } from './browserAdapter';
import type {
  BookmarkSyncPreviewChange,
  BookmarkSyncPreviewData,
  BookmarkSyncProfileResource,
  NormalizedBookmarkResource,
} from './types';

type BookmarkActionLog = {
  action: string;
  title?: string | null;
  url?: string | null;
  path?: string[];
};

export type NativeBookmarkSyncResult = {
  preview: BookmarkSyncPreviewData;
  operationLog: BookmarkActionLog[];
};

type NativeBookmarkEntry = {
  id: string;
  parentId?: string;
  title: string;
  url: string;
  path: string[];
};

const MANAGED_ROOT_FOLDER = 'BrowserBridge';
const ROOT_FOLDER_NAMES = new Set(['bookmarks bar', 'other bookmarks', 'mobile bookmarks']);

export async function previewNativeBookmarkSync(
  profile: BookmarkSyncProfileResource,
  sourceBookmarks: NormalizedBookmarkResource[],
  adapter: BrowserAdapter = getBrowserAdapter(),
): Promise<BookmarkSyncPreviewData> {
  ensureNativeWriteAvailable(adapter);

  if (profile.mode === 'mirror' && profile.target_scope === 'entire_bookmarks_root') {
    throw new Error('Full-root Mirror is disabled in this build. Choose a BrowserBridge-managed folder or selected folder.');
  }

  const source = uniqueValidSourceBookmarks(sourceBookmarks);
  const duplicateCount = duplicateSourceUrlCount(sourceBookmarks);
  const invalidCount = sourceBookmarks.filter((bookmark) => !bookmark.url).length;
  const scopeNode = await findDestinationScope(profile, adapter, false);
  const existingEntries = scopeNode ? collectBookmarkEntries(scopeNode) : [];
  const existingByUrl = mapByNormalizedUrl(existingEntries);
  const sourceByUrl = mapSourceByNormalizedUrl(source);
  const sampleChanges: BookmarkSyncPreviewChange[] = [];
  let addCount = 0;
  let updateCount = 0;
  let moveCount = 0;
  let deleteCount = 0;
  let skipCount = duplicateCount;

  for (const bookmark of source) {
    const existing = existingByUrl.get(normalizeUrl(bookmark.url || ''));

    if (!existing) {
      addCount++;
      sampleChanges.push(changeFromBookmark('add', bookmark));
      continue;
    }

    if (profile.mode === 'safe_folder' || profile.mode === 'merge') {
      skipCount++;
      continue;
    }

    if ((bookmark.title || bookmark.url) && existing.title !== (bookmark.title || bookmark.url)) {
      updateCount++;
      sampleChanges.push(changeFromBookmark('update', bookmark));
    }

    const expectedPath = pathForBookmark(profile, bookmark);

    if (!samePath(existing.path, expectedPath)) {
      moveCount++;
      sampleChanges.push(changeFromBookmark('move', bookmark));
    }
  }

  if (profile.mode === 'mirror') {
    for (const existing of existingEntries) {
      if (!sourceByUrl.has(normalizeUrl(existing.url))) {
        deleteCount++;
        sampleChanges.push({
          action: 'delete',
          title: existing.title,
          url: existing.url,
          path: existing.path,
        });
      }
    }
  }

  return {
    mode: profile.mode,
    source_device: profile.source_device?.name || null,
    target_device: profile.target_device?.name || null,
    add_count: addCount,
    update_count: updateCount,
    move_count: moveCount,
    delete_count: deleteCount,
    skip_count: skipCount,
    duplicate_count: duplicateCount,
    invalid_count: invalidCount,
    warnings: warningsForProfile(profile, adapter),
    sample_changes: sampleChanges.slice(0, 12),
  };
}

export async function applyNativeBookmarkSync(
  profile: BookmarkSyncProfileResource,
  sourceBookmarks: NormalizedBookmarkResource[],
  adapter: BrowserAdapter = getBrowserAdapter(),
): Promise<NativeBookmarkSyncResult> {
  const preview = await previewNativeBookmarkSync(profile, sourceBookmarks, adapter);
  const source = uniqueValidSourceBookmarks(sourceBookmarks);
  const scopeFolder = await findDestinationScope(profile, adapter, true);
  const operationLog: BookmarkActionLog[] = [];

  if (!scopeFolder) {
    throw new Error('Could not prepare the destination bookmark folder.');
  }

  const currentEntries = collectBookmarkEntries(await refreshNode(scopeFolder.id, adapter));
  const existingByUrl = mapByNormalizedUrl(currentEntries);
  const sourceByUrl = mapSourceByNormalizedUrl(source);

  for (const bookmark of source) {
    const url = bookmark.url;

    if (!url) {
      continue;
    }

    const existing = existingByUrl.get(normalizeUrl(url));

    if (existing && (profile.mode === 'safe_folder' || profile.mode === 'merge')) {
      operationLog.push(logFromBookmark('skip_duplicate', bookmark));
      continue;
    }

    const targetFolder = await findOrCreateFolderWithin(scopeFolder.id, pathForBookmark(profile, bookmark), adapter);

    if (!existing) {
      await adapter.createNativeBookmark(targetFolder.id, bookmark.title || url, url);
      operationLog.push(logFromBookmark('add', bookmark));
      continue;
    }

    if (profile.mode !== 'mirror') {
      continue;
    }

    if ((bookmark.title || url) && existing.title !== (bookmark.title || url)) {
      await adapter.updateNativeBookmark(existing.id, { title: bookmark.title || url, url });
      operationLog.push(logFromBookmark('update', bookmark));
    }

    if (existing.parentId !== targetFolder.id) {
      await adapter.moveNativeBookmark(existing.id, { parentId: targetFolder.id });
      operationLog.push(logFromBookmark('move', bookmark));
    }
  }

  if (profile.mode === 'mirror') {
    for (const existing of currentEntries) {
      if (!sourceByUrl.has(normalizeUrl(existing.url))) {
        await adapter.removeNativeBookmarkTree(existing.id);
        operationLog.push({
          action: 'delete',
          title: existing.title,
          url: existing.url,
          path: existing.path,
        });
      }
    }
  }

  return { preview, operationLog };
}

export async function exportNativeBookmarkBackup(adapter: BrowserAdapter = getBrowserAdapter()): Promise<chrome.bookmarks.BookmarkTreeNode[]> {
  ensureNativeWriteAvailable(adapter);

  return adapter.getNativeBookmarkTree();
}

function ensureNativeWriteAvailable(adapter: BrowserAdapter): void {
  if (!adapter.supportsNativeBookmarkWrite()) {
    throw new Error('Native Safari bookmark writing is not available in this Safari version. You can still view BrowserBridge bookmarks and send tabs.');
  }
}

async function findDestinationScope(
  profile: BookmarkSyncProfileResource,
  adapter: BrowserAdapter,
  create: boolean,
): Promise<chrome.bookmarks.BookmarkTreeNode | null> {
  if (profile.target_scope === 'entire_bookmarks_root') {
    throw new Error('Full-root Mirror is disabled in this build. Choose a BrowserBridge-managed folder or selected folder.');
  }

  if (profile.target_scope === 'selected_folder' && profile.selected_target_folder_id) {
    const node = await findNodeById(profile.selected_target_folder_id, adapter);

    if (!node || node.url) {
      throw new Error('Selected bookmark folder was not found.');
    }

    return node;
  }

  const path = scopeFolderPath(profile);

  return create ? adapter.findOrCreateNativeFolderPath(path) : adapter.findNativeFolderByPath(path);
}

function scopeFolderPath(profile: BookmarkSyncProfileResource): string[] {
  if (profile.target_scope === 'selected_folder' && profile.selected_target_folder_id) {
    return [];
  }

  if (profile.mode === 'merge') {
    return [MANAGED_ROOT_FOLDER, 'Merged bookmarks'];
  }

  return [MANAGED_ROOT_FOLDER, sourceDeviceFolderName(profile)];
}

function sourceDeviceFolderName(profile: BookmarkSyncProfileResource): string {
  return sanitizeFolderSegment(profile.source_device?.name || `Device ${profile.source_device_id}`);
}

function pathForBookmark(profile: BookmarkSyncProfileResource, bookmark: NormalizedBookmarkResource): string[] {
  if (profile.target_scope === 'selected_folder' && profile.selected_target_folder_id) {
    return sanitizeBookmarkPath(bookmark.path);
  }

  return sanitizeBookmarkPath(bookmark.path);
}

function sanitizeBookmarkPath(path: string[]): string[] {
  return path
    .map(sanitizeFolderSegment)
    .filter((segment) => segment && !ROOT_FOLDER_NAMES.has(segment.toLowerCase()) && segment !== MANAGED_ROOT_FOLDER);
}

function sanitizeFolderSegment(segment: string): string {
  return segment.replace(/[\\/]/g, '-').trim();
}

function uniqueValidSourceBookmarks(bookmarks: NormalizedBookmarkResource[]): NormalizedBookmarkResource[] {
  const seen = new Set<string>();
  const result: NormalizedBookmarkResource[] = [];

  for (const bookmark of bookmarks) {
    if (!bookmark.url) {
      continue;
    }

    const normalizedUrl = normalizeUrl(bookmark.url);

    if (seen.has(normalizedUrl)) {
      continue;
    }

    seen.add(normalizedUrl);
    result.push(bookmark);
  }

  return result;
}

function duplicateSourceUrlCount(bookmarks: NormalizedBookmarkResource[]): number {
  const seen = new Set<string>();
  let duplicates = 0;

  for (const bookmark of bookmarks) {
    if (!bookmark.url) {
      continue;
    }

    const normalizedUrl = normalizeUrl(bookmark.url);

    if (seen.has(normalizedUrl)) {
      duplicates++;
    } else {
      seen.add(normalizedUrl);
    }
  }

  return duplicates;
}

function collectBookmarkEntries(root: chrome.bookmarks.BookmarkTreeNode): NativeBookmarkEntry[] {
  const entries: NativeBookmarkEntry[] = [];

  const visit = (node: chrome.bookmarks.BookmarkTreeNode, path: string[]): void => {
    if (node.url) {
      entries.push({
        id: node.id,
        parentId: node.parentId,
        title: node.title || node.url,
        url: node.url,
        path,
      });

      return;
    }

    const nextPath = node.id === root.id ? path : [...path, node.title].filter(Boolean);
    node.children?.forEach((child) => visit(child, nextPath));
  };

  root.children?.forEach((child) => visit(child, []));

  return entries;
}

async function refreshNode(id: string, adapter: BrowserAdapter): Promise<chrome.bookmarks.BookmarkTreeNode> {
  const tree = await adapter.getNativeBookmarkTree();
  const node = findNodeInTree(tree, id);

  if (!node) {
    throw new Error('Destination bookmark folder was not found.');
  }

  return node;
}

async function findOrCreateFolderWithin(
  scopeFolderId: string,
  path: string[],
  adapter: BrowserAdapter,
): Promise<chrome.bookmarks.BookmarkTreeNode> {
  let parent = await refreshNode(scopeFolderId, adapter);

  for (const segment of path) {
    const existing = parent.children?.find((child) => !child.url && child.title === segment);

    if (existing) {
      parent = await refreshNode(existing.id, adapter);
      continue;
    }

    parent = await adapter.createNativeFolder(parent.id, segment);
  }

  return parent;
}

async function findNodeById(id: string, adapter: BrowserAdapter): Promise<chrome.bookmarks.BookmarkTreeNode | null> {
  const tree = await adapter.getNativeBookmarkTree();

  return findNodeInTree(tree, id);
}

function findNodeInTree(nodes: chrome.bookmarks.BookmarkTreeNode[], id: string): chrome.bookmarks.BookmarkTreeNode | null {
  for (const node of nodes) {
    if (node.id === id) {
      return node;
    }

    const child = node.children ? findNodeInTree(node.children, id) : null;

    if (child) {
      return child;
    }
  }

  return null;
}

function mapByNormalizedUrl(entries: NativeBookmarkEntry[]): Map<string, NativeBookmarkEntry> {
  return entries.reduce((map, entry) => {
    const normalizedUrl = normalizeUrl(entry.url);

    if (!map.has(normalizedUrl)) {
      map.set(normalizedUrl, entry);
    }

    return map;
  }, new Map<string, NativeBookmarkEntry>());
}

function mapSourceByNormalizedUrl(bookmarks: NormalizedBookmarkResource[]): Map<string, NormalizedBookmarkResource> {
  return bookmarks.reduce((map, bookmark) => {
    if (bookmark.url) {
      map.set(normalizeUrl(bookmark.url), bookmark);
    }

    return map;
  }, new Map<string, NormalizedBookmarkResource>());
}

function samePath(left: string[], right: string[]): boolean {
  return left.join('\u0000') === right.join('\u0000');
}

function normalizeUrl(url: string): string {
  return url.trim().toLowerCase().replace(/\/+$/, '');
}

function changeFromBookmark(action: BookmarkSyncPreviewChange['action'], bookmark: NormalizedBookmarkResource): BookmarkSyncPreviewChange {
  return {
    action,
    title: bookmark.title,
    url: bookmark.url,
    path: sanitizeBookmarkPath(bookmark.path),
  };
}

function logFromBookmark(action: string, bookmark: NormalizedBookmarkResource): BookmarkActionLog {
  return {
    action,
    title: bookmark.title,
    url: bookmark.url,
    path: sanitizeBookmarkPath(bookmark.path),
  };
}

function warningsForProfile(profile: BookmarkSyncProfileResource, adapter: BrowserAdapter): string[] {
  const warnings: string[] = [];

  if (profile.mode === 'safe_folder') {
    warnings.push('Safe Folder Import only writes inside a BrowserBridge-managed folder and never deletes native bookmarks.');
  }

  if (profile.mode === 'merge') {
    warnings.push('Merge adds missing bookmarks and does not delete anything.');
  }

  if (profile.mode === 'mirror') {
    warnings.push('Mirror may delete or move bookmarks in the selected destination scope. BrowserBridge will create a backup first.');
  }

  if (!adapter.supportsNativeBookmarkWrite()) {
    warnings.push('Native Safari bookmark writing is not available in this Safari version. You can still view BrowserBridge bookmarks and send tabs.');
  }

  return warnings;
}
