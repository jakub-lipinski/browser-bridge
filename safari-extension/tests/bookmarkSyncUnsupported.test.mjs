import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Safari adapter implements runtime bookmark source detection', () => {
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(source, /canReadNativeBookmarks/);
  assert.match(source, /bookmarks\.getTree/);
  assert.match(source, /async getBookmarksTree/);
  assert.match(source, /external_id: node\.id/);
  assert.match(source, /parent_external_id: node\.parentId/);
});

test('Safari manifest asks for source permissions while code degrades cleanly', () => {
  const manifest = readFileSync(new URL('../public/manifest.json', import.meta.url), 'utf8');
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(manifest, /"bookmarks"/);
  assert.match(manifest, /"history"/);
  assert.match(source, /Native Safari bookmark reading is not available in this Safari version/);
});

test('Safari options explain runtime detection and Activity fallback', () => {
  const options = readFileSync(new URL('../src/options.html', import.meta.url), 'utf8');

  assert.match(options, /checks Safari bookmark and history APIs at runtime/);
  assert.match(options, /Native Safari bookmark reading: Runtime detected in the popup/);
  assert.match(options, /Native Safari history reading: Runtime detected in the popup/);
  assert.match(options, /BrowserBridge Activity: Available after opt-in/);
});
