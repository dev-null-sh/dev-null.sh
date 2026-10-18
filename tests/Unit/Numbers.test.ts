import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatCount } from '../../resources/js/lib/numbers.ts';

void test('counts below a thousand are shown in full', () => {
    assert.equal(formatCount(0), '0');
    assert.equal(formatCount(7), '7');
    assert.equal(formatCount(999), '999');
});

void test('counts from a thousand are shortened with a lowercase suffix', () => {
    assert.equal(formatCount(1000), '1k');
    assert.equal(formatCount(1001), '1k');
    assert.equal(formatCount(1500), '1.5k');
    assert.equal(formatCount(10_000), '10k');
    assert.equal(formatCount(999_999), '1m');
    assert.equal(formatCount(1_000_000), '1m');
    assert.equal(formatCount(2_300_000), '2.3m');
});
