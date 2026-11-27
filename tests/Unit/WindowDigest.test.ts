import assert from 'node:assert/strict';
import { createHash } from 'node:crypto';
import { test } from 'node:test';
import { windowDigest } from '../../resources/js/lib/window-digest.ts';

void test('names a window by its members, whatever their order, as the server does', async () => {
    const ids = ['01a1a1f8-e1dc-701b-82f8-4f2c562078e5', '01a1a184-4b39-70e5-bdce-16e403d127f9', '01a1a1d6-6d0a-7105-976d-947ea8f3b258'];
    const sorted = [...ids].sort();

    assert.equal(await windowDigest(ids), createHash('sha256').update(sorted.join(',')).digest('hex'));
    assert.equal(await windowDigest([...ids].reverse()), await windowDigest(ids));
    assert.notEqual(await windowDigest(ids.slice(1)), await windowDigest(ids));
});
