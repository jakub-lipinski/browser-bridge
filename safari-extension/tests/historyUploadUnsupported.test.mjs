import { readFileSync } from 'node:fs';
import { test } from 'node:test';
import assert from 'node:assert/strict';

test('Safari adapter does not advertise native history upload support', () => {
  const source = readFileSync(new URL('../src/modules/safariBrowserAdapter.ts', import.meta.url), 'utf8');

  assert.match(source, /supportsHistory\(\): boolean\s*{\s*return false;/);
});
