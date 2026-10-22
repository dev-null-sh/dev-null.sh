import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    agendaProgress,
    eventFormat,
    eventStateAt,
} from '../../resources/js/lib/event.ts';
import type { Event } from '../../resources/js/types/event.ts';

const event: Event = {
    id: 'event',
    commit_id: 'commit',
    title: 'PHP night',
    starts_at: '2026-11-04T18:00:00Z',
    ends_at: '2026-11-04T21:00:00Z',
    location: 'Lisbon',
    url: null,
    cancelled_at: null,
    state: 'upcoming',
};

const items = [
    { id: 1, label: 'Doors', starts_at: '2026-11-04T18:00:00Z' },
    { id: 2, label: 'Octane', starts_at: '2026-11-04T19:00:00Z' },
    { id: 3, label: 'Pint', starts_at: '2026-11-04T20:00:00Z' },
];

void test('the state follows the clock, not the state the page was served with', () => {
    assert.equal(
        eventStateAt(event, new Date('2026-11-04T17:59:59Z')),
        'upcoming',
    );
    assert.equal(eventStateAt(event, new Date('2026-11-04T18:00:00Z')), 'live');
    assert.equal(
        eventStateAt(event, new Date('2026-11-04T21:00:00Z')),
        'ended',
    );
    assert.equal(
        eventStateAt(
            { ...event, cancelled_at: '2026-11-01T00:00:00Z' },
            new Date('2026-11-04T19:00:00Z'),
        ),
        'cancelled',
    );
});

void test('nothing on the agenda is done before the event', () => {
    assert.deepEqual(
        agendaProgress(items, event, new Date('2026-11-04T17:00:00Z')),
        { done: 0, current: null },
    );
});

void test('an agenda item is done once the next one starts', () => {
    assert.deepEqual(
        agendaProgress(items, event, new Date('2026-11-04T19:30:00Z')),
        { done: 1, current: 2 },
    );
});

void test('the last agenda item is done once the event ends', () => {
    assert.deepEqual(
        agendaProgress(items, event, new Date('2026-11-04T20:59:00Z')),
        { done: 2, current: 3 },
    );
    assert.deepEqual(
        agendaProgress(items, event, new Date('2026-11-04T21:00:00Z')),
        { done: 3, current: null },
    );
});

void test('a place, a link or both make an in-person, online or hybrid event', () => {
    assert.equal(eventFormat({ location: 'Lisbon', url: null }), 'In person');
    assert.equal(
        eventFormat({ location: null, url: 'https://x.dev' }),
        'Online',
    );
    assert.equal(
        eventFormat({ location: 'Lisbon', url: 'https://x.dev' }),
        'Hybrid',
    );
});
