import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatUtcOffset } from '../../resources/js/lib/utils.ts';

void test('the offset follows daylight saving time of the timezone', () => {
    assert.equal(
        formatUtcOffset(new Date('2026-07-01T12:00:00Z'), 'Europe/Berlin'),
        'UTC +02:00',
    );
    assert.equal(
        formatUtcOffset(new Date('2026-01-01T12:00:00Z'), 'Europe/Berlin'),
        'UTC +01:00',
    );
    assert.equal(
        formatUtcOffset(new Date('2026-01-01T12:00:00Z'), 'America/New_York'),
        'UTC -05:00',
    );
});

void test('a timezone without an offset is plain UTC', () => {
    assert.equal(
        formatUtcOffset(new Date('2026-07-01T12:00:00Z'), 'UTC'),
        'UTC',
    );
});
