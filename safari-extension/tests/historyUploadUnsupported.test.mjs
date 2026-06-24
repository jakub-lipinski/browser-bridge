import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Safari adapter probes native history and supports Activity fallback', () => {
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(source, /canReadNativeHistory/);
  assert.match(source, /history\.search/);
  assert.match(source, /getActivitySince/);
  assert.match(source, /historyMode: canReadNativeHistory \? 'native'/);
  assert.match(source, /supportsHistory\(\): boolean\s*{\s*return true;/);
});

test('Safari sync uploads available source data before refreshing remote data', () => {
  const source = readFileSync(new URL('../src/background.ts', import.meta.url), 'utf8');

  assert.match(source, /browserbridge\.refreshNow/);
  assert.match(source, /uploadSafariSources/);
  assert.match(source, /syncBookmarks/);
  assert.match(source, /syncHistory/);
  assert.match(source, /BrowserBridge can still save Safari pages you send\/open through the extension/);
});

test('Safari popup labels runtime capabilities clearly', () => {
  const popup = readFileSync(new URL('../src/popup.html', import.meta.url), 'utf8');
  const source = readFileSync(new URL('../src/popup.ts', import.meta.url), 'utf8');

  assert.match(popup, />Refresh<\/button>/);
  assert.match(popup, /Native Safari bookmark reading: Checking/);
  assert.match(popup, /Native Safari history reading: Checking/);
  assert.match(popup, /BrowserBridge Activity is not the same as full Safari history/);
  assert.match(popup, /id="capability-debug-list"/);
  assert.match(source, /syncButtonLabel/);
  assert.match(source, /Safari sync complete/);
});

test('Safari popup prioritizes tab handoff before long data lists', () => {
  const popup = readFileSync(new URL('../src/popup.html', import.meta.url), 'utf8');

  assert.ok(popup.indexOf('id="send-card"') < popup.indexOf('id="bookmarks-panel"'));
  assert.ok(popup.indexOf('id="incoming-panel"') < popup.indexOf('id="bookmarks-panel"'));
  assert.match(popup, /data-tab="send"/);
  assert.match(popup, /data-tab="bookmarks"/);
  assert.match(popup, /data-tab="history"/);
  assert.match(popup, /data-tab="settings"/);
  assert.doesNotMatch(popup, /data-tab="incoming"/);
});

test('Safari popup uses bounded result lists and debounced dynamic search', () => {
  const source = readFileSync(new URL('../src/popup.ts', import.meta.url), 'utf8');

  assert.match(source, /VISIBLE_RESULT_LIMIT = 5/);
  assert.match(source, /SEARCH_DEBOUNCE_MS = 250/);
  assert.match(source, /REMOTE_SEARCH_MIN_LENGTH = 2/);
  assert.match(source, /scheduleBookmarkSearch/);
  assert.match(source, /scheduleHistorySearch/);
});

test('Safari popup shows calm in-popup send feedback without relying on browser badges', () => {
  const popup = readFileSync(new URL('../src/popup.html', import.meta.url), 'utf8');
  const source = readFileSync(new URL('../src/popup.ts', import.meta.url), 'utf8');

  assert.match(popup, /id="send-toast"/);
  assert.match(popup, /id="activity-dot"/);
  assert.match(source, /showSentFeedback/);
});
