import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    exactCountTitle,
    formatCount,
    formatExactCount,
} from '../../resources/js/lib/numbers.ts';

void test('counts below a thousand are shown in full', () => {
    assert.equal(formatCount(0), '0');
    assert.equal(formatCount(7), '7');
    assert.equal(formatCount(999), '999');
});

void test('counts from a thousand get thousands separators', () => {
    assert.equal(formatCount(1000), '1,000');
    assert.equal(formatCount(1250), '1,250');
    assert.equal(formatCount(9999), '9,999');
});

void test('counts from ten thousand are shortened with a lowercase suffix', () => {
    assert.equal(formatCount(10_000), '10k');
    assert.equal(formatCount(10_250), '10.2k');
    assert.equal(formatCount(999_999), '999.9k');
    assert.equal(formatCount(1_000_000), '1m');
    assert.equal(formatCount(2_300_000), '2.3m');
});

void test('exact counts keep every digit with thousands separators', () => {
    assert.equal(formatExactCount(999), '999');
    assert.equal(formatExactCount(10_250), '10,250');
    assert.equal(formatExactCount(1_234_567), '1,234,567');
});

void test('the exact count shows on hover only when the count is shortened', () => {
    assert.equal(exactCountTitle(9999), undefined);
    assert.equal(exactCountTitle(10_000), '10,000');
    assert.equal(exactCountTitle(12_345), '12,345');
});
