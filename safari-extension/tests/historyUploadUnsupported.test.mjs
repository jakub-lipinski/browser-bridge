import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Safari adapter does not advertise native history upload support', () => {
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(source, /supportsHistory\(\): boolean\s*{\s*return false;/);
});

test('Safari refresh does not upload native history or bookmarks', () => {
  const source = readFileSync(new URL('../src/background.ts', import.meta.url), 'utf8');

  assert.match(source, /browserbridge\.refreshNow/);
  assert.doesNotMatch(source, /syncHistory/);
  assert.doesNotMatch(source, /syncBookmarks/);
});

test('Safari popup labels server refresh and capability limitations clearly', () => {
  const popup = readFileSync(new URL('../src/popup.html', import.meta.url), 'utf8');

  assert.match(popup, />Refresh<\/button>/);
  assert.match(popup, /Display BrowserBridge Bookmarks: Available/);
  assert.match(popup, /Display BrowserBridge History: Available/);
  assert.match(popup, /Upload native Safari bookmarks: Not available in this version/);
  assert.match(popup, /Upload native Safari history: Not available in this version/);
});
