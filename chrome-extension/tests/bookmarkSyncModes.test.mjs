import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Chrome adapter exposes native bookmark write operations', () => {
  const adapter = readFileSync(new URL('../src/modules/chromiumBrowserAdapter.ts', import.meta.url), 'utf8');

  for (const operation of [
    'getNativeBookmarkTree',
    'findNativeFolderByPath',
    'findOrCreateNativeFolderPath',
    'createNativeFolder',
    'createNativeBookmark',
    'updateNativeBookmark',
    'moveNativeBookmark',
    'removeNativeBookmarkTree',
  ]) {
    assert.match(adapter, new RegExp(`async ${operation}`));
  }

  assert.match(adapter, /supportsNativeBookmarkWrite\(\): boolean\s*{\s*return true;/);
});

test('bookmark sync modes keep destructive work behind Mirror', () => {
  const source = readFileSync(new URL('../src/modules/bookmarkSyncModes.ts', import.meta.url), 'utf8');

  assert.match(source, /Safe Folder Import only writes inside a BrowserBridge-managed folder/);
  assert.match(source, /Merge adds missing bookmarks and does not delete anything/);
  assert.match(source, /profile\.mode === 'mirror'/);
  assert.match(source, /removeNativeBookmarkTree/);
  assert.match(source, /Full-root Mirror is disabled in this build/);
});

test('Chrome options expose bookmark sync profile controls and mirror warning', () => {
  const options = readFileSync(new URL('../src/options.html', import.meta.url), 'utf8');

  assert.match(options, /Safe Folder Import — Recommended/);
  assert.match(options, /Merge — Advanced/);
  assert.match(options, /Mirror — Dangerous/);
  assert.match(options, /id="bookmark-sync-preview"/);
  assert.match(options, /id="bookmark-sync-run"/);
  assert.match(options, /Every 15 minutes/);
  assert.match(options, /Mirror may delete or move bookmarks/);
});

test('automatic bookmark sync skips Mirror profiles', () => {
  const background = readFileSync(new URL('../src/background.ts', import.meta.url), 'utf8');

  assert.match(background, /runDueBookmarkSyncProfiles/);
  assert.match(background, /profile\.mode === 'mirror'/);
  assert.match(background, /Skipping automatic Mirror bookmark sync/);
});
