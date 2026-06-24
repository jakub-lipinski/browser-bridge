import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Chrome popup prioritizes tab handoff before long data lists', () => {
  const popup = readFileSync(new URL('../src/popup.html', import.meta.url), 'utf8');

  assert.ok(popup.indexOf('id="send-card"') < popup.indexOf('id="bookmarks-panel"'));
  assert.ok(popup.indexOf('id="incoming-panel"') < popup.indexOf('id="bookmarks-panel"'));
  assert.match(popup, /data-tab="send"/);
  assert.match(popup, /data-tab="incoming"/);
  assert.match(popup, /data-tab="bookmarks"/);
  assert.match(popup, /data-tab="history"/);
  assert.match(popup, /data-tab="settings"/);
});

test('Chrome popup uses bounded result lists and debounced dynamic search', () => {
  const source = readFileSync(new URL('../src/popup.ts', import.meta.url), 'utf8');

  assert.match(source, /VISIBLE_RESULT_LIMIT = 5/);
  assert.match(source, /SEARCH_DEBOUNCE_MS = 250/);
  assert.match(source, /REMOTE_SEARCH_MIN_LENGTH = 2/);
  assert.match(source, /scheduleBookmarkSearch/);
  assert.match(source, /scheduleHistorySearch/);
});
