import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('history sync exits before reading browser history when disabled', () => {
  const source = readFileSync(new URL('../src/modules/historySync.ts', import.meta.url), 'utf8');
  const disabledGuard = source.indexOf('if (!config.sync.history)');
  const adapterAccess = source.indexOf('getBrowserAdapter()');

  assert.notEqual(disabledGuard, -1);
  assert.notEqual(adapterAccess, -1);
  assert.ok(disabledGuard < adapterAccess);
});
