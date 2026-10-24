import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatUserCode } from '../../resources/js/lib/user-code.ts';

void test('a code is shown in uppercase, with its dash after the fourth letter', () => {
    assert.equal(formatUserCode('bcdfghjk'), 'BCDF-GHJK');
    assert.equal(formatUserCode('BCDF-GHJK'), 'BCDF-GHJK');
    assert.equal(formatUserCode('bcdf ghjk'), 'BCDF-GHJK');
});

void test('what is not one of its letters is left out', () => {
    assert.equal(formatUserCode('a1 bcdf-ghjk!'), 'BCDF-GHJK');
    assert.equal(formatUserCode('aeiouy'), '');
});

void test('a code stops at eight letters', () => {
    assert.equal(formatUserCode('bcdfghjklmnp'), 'BCDF-GHJK');
});

void test('a code being typed keeps its shape', () => {
    assert.equal(formatUserCode(''), '');
    assert.equal(formatUserCode('bcd'), 'BCD');
    assert.equal(formatUserCode('bcdf'), 'BCDF');
    assert.equal(formatUserCode('bcdfg'), 'BCDF-G');
});
