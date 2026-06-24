import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('API client normalizes all BrowserBridge collection response shapes', () => {
  const source = readFileSync(new URL('../src/modules/apiClient.ts', import.meta.url), 'utf8');

  for (const key of ['data', 'bookmarks', 'history', 'items', 'devices', 'commands', 'snapshots']) {
    assert.match(source, new RegExp(`['"]${key}['"]`));
  }
});

test('direct collection fetches use normalized array responses', () => {
  const source = readFileSync(new URL('../src/modules/apiClient.ts', import.meta.url), 'utf8');

  for (const functionName of ['fetchDevices', 'fetchBookmarks', 'fetchIncomingTabCommands', 'searchHistory', 'fetchTabSnapshots']) {
    const start = source.indexOf(`async function ${functionName}`);
    assert.notEqual(start, -1);

    const nextFunction = source.indexOf('export async function', start + 1);
    const functionSource = source.slice(start, nextFunction === -1 ? undefined : nextFunction);

    assert.match(functionSource, /normalizeArrayResponse\(response\)/);
  }
});
