import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    agendaProgress,
    eventFormat,
    eventPlace,
    eventStateAt,
    timeZoneLabel,
    wallTimeToInstant,
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

void test('reads a datetime-local value on the clock of the given zone', () => {
    assert.equal(
        wallTimeToInstant('2026-11-03T19:00', 'Europe/Lisbon'),
        '2026-11-03T19:00:00.000Z',
    );
    assert.equal(
        wallTimeToInstant('2026-07-03T19:00', 'Europe/Lisbon'),
        '2026-07-03T18:00:00.000Z',
    );
    assert.equal(
        wallTimeToInstant('2026-11-03T19:00', 'America/New_York'),
        '2026-11-04T00:00:00.000Z',
    );
    assert.equal(
        wallTimeToInstant('2026-11-03T19:00', 'Asia/Kolkata'),
        '2026-11-03T13:30:00.000Z',
    );
    assert.equal(wallTimeToInstant('', 'Europe/Lisbon'), '');
});

void test('reads a time on the day the clocks go forward after the change', () => {
    assert.equal(
        wallTimeToInstant('2026-03-29T03:30', 'Europe/Paris'),
        '2026-03-29T01:30:00.000Z',
    );
});

void test('names the zone a time follows', () => {
    assert.match(
        timeZoneLabel(new Date('2026-11-03T19:00:00Z'), 'Asia/Kolkata'),
        /GMT\+5:30|IST/u,
    );
});

void test('takes a place that only says online as no place', () => {
    assert.equal(eventPlace({ location: ' Online ' }), null);
    assert.equal(eventPlace({ location: 'Lisbon' }), 'Lisbon');
    assert.equal(
        eventFormat({ location: 'online', url: 'https://youtube.com/live' }),
        'Online',
    );
});
