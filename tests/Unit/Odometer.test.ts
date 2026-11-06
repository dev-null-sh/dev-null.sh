import assert from 'node:assert/strict';
import { test } from 'node:test';
import { odometerPlaces } from '../../resources/js/lib/odometer.ts';

// A space is a place the count does not have; `·` makes it visible in the expectations.
const shown = (previous: string, current: string): string[] =>
    odometerPlaces(previous, current).map(
        ({ char, previousChar, isRolling }) => {
            const place = (value: string) => (value === ' ' ? '·' : value);

            return isRolling
                ? `${place(previousChar)}→${place(char)}`
                : place(char);
        },
    );

void test('leaves every place still when the count does not change', () => {
    assert.deepEqual(shown('1,250', '1,250'), ['1', ',', '2', '5', '0']);
});

void test('rolls only the digit that changes', () => {
    assert.deepEqual(shown('120', '121'), ['1', '2', '0→1']);
    assert.deepEqual(shown('121', '120'), ['1', '2', '1→0']);
});

void test('rolls a new place in when the count grows', () => {
    assert.deepEqual(shown('9', '10'), ['·→1', '9→0']);
    assert.deepEqual(shown('99', '100'), ['·→1', '9→0', '9→0']);
});

void test('rolls the old place out when the count shrinks', () => {
    assert.deepEqual(shown('10', '9'), ['1→·', '0→9']);
    assert.deepEqual(shown('100', '99'), ['1→·', '0→9', '0→9']);
});

void test('leaves the thousands separator still while the digits roll', () => {
    assert.deepEqual(shown('999', '1,000'), ['·→1', ',', '9→0', '9→0', '9→0']);
});

void test("leaves a shortened count's suffix still while the digits roll", () => {
    // Covers both directions: the comma and `k` stay still; a digit replacing `k` also stays still.
    assert.deepEqual(shown('9,999', '10k'), ['9→·', '·', '9→1', '9→0', 'k']);
    assert.deepEqual(shown('10k', '9,999'), ['·→9', ',', '1→9', '0→9', '9']);
});

void test('rolls the first digit in and out when the count appears or disappears', () => {
    assert.deepEqual(shown('', '1'), ['·→1']);
    assert.deepEqual(shown('1', ''), ['1→·']);
    assert.deepEqual(shown('', ''), []);
});
