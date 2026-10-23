import assert from 'node:assert/strict';
import { test } from 'node:test';
import { isBehindLatest } from '../../resources/js/lib/chat-scroll.ts';
import type { Message } from '../../resources/js/types/index.ts';

const messages = (count: number): Message[] => Array.from({ length: count }, (_, index) => ({ id: index + 1, event: null }) as Message);

function scroller(tops: Record<number, number>, bottom = 600): HTMLElement {
    return {
        getBoundingClientRect: () => ({ bottom }),
        querySelector: (selector: string) => {
            const id = Number(selector.match(/\d+/)?.[0]);
            return tops[id] === undefined ? null : { getBoundingClientRect: () => ({ top: tops[id] }) };
        },
    } as unknown as HTMLElement;
}

test('offers the present when all 25 newest messages are below the viewport', () => {
    assert.equal(isBehindLatest(scroller({ 6: 600 }), messages(30)), true);
    assert.equal(isBehindLatest(scroller({ 6: 601 }), messages(30)), true);
});

test('hides the shortcut as soon as any of the newest 25 messages is visible', () => {
    assert.equal(isBehindLatest(scroller({ 6: 599 }), messages(30)), false);
    assert.equal(isBehindLatest(scroller({ 6: -1500 }), messages(30)), false);
});

test('does not offer a scroll shortcut for fewer than 25 messages or missing rows', () => {
    assert.equal(isBehindLatest(scroller({ 1: 900 }), messages(24)), false);
    assert.equal(isBehindLatest(scroller({}), messages(30)), false);
    assert.equal(isBehindLatest(scroller({}), []), false);
});

test('counts messages rather than org events or gaps in server ids', () => {
    const rows = messages(30).map((message) => ({ ...message, id: message.id * 10 }));
    rows.push({ id: 999, event: { type: 'joined' } } as unknown as Message);

    assert.equal(isBehindLatest(scroller({ 60: 601, 70: 599 }), rows), true);
});

test('uses the actual viewport edge and bubble height instead of a fixed pixel distance', () => {
    assert.equal(isBehindLatest(scroller({ 6: 430 }, 420), messages(30)), true);
    assert.equal(isBehindLatest(scroller({ 6: 430 }, 650), messages(30)), false);
});
