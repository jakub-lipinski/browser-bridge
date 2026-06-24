import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Safari adapter rejects native bookmark writing operations', () => {
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(source, /supportsNativeBookmarkWrite\(\): boolean\s*{\s*return false;/);
  assert.match(source, /Native Safari bookmark writing is not available in this Safari version/);
});

test('Safari options explain bookmark sync mode limitations', () => {
  const options = readFileSync(new URL('../src/options.html', import.meta.url), 'utf8');

  assert.match(options, /Native Safari bookmark writing is not available in this Safari version/);
  assert.match(options, /Safe Folder Import: Not available for native Safari bookmarks/);
  assert.match(options, /Merge: Not available for native Safari bookmarks/);
  assert.match(options, /Mirror: Disabled for Safari/);
});
