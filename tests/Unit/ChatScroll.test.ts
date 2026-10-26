import assert from 'node:assert/strict';
import { after, test } from 'node:test';
import { isBehindLatest } from '../../resources/js/lib/chat-scroll.ts';
import type { Message } from '../../resources/js/types/index.ts';

const originalCSS = globalThis.CSS;
globalThis.CSS = { escape: (value: string) => value } as typeof CSS;
after(() => { globalThis.CSS = originalCSS; });

const messages = (count: number): Message[] => Array.from({ length: count }, (_, index) => ({ id: index + 1, client_id: `message-${index + 1}`, event: null }) as Message);

function scroller(tops: Record<number, number>, bottom = 600, textTops = tops): HTMLElement {
    return {
        getBoundingClientRect: () => ({ bottom }),
        querySelector: (selector: string) => {
            const id = Number(selector.match(/\d+/)?.[0]);
            const positions = selector.startsWith('[data-message-id=') ? textTops : tops;
            return positions[id] === undefined ? null : { getBoundingClientRect: () => ({ top: positions[id] }) };
        },
    } as unknown as HTMLElement;
}

void test('offers the present when all 25 newest messages are below the viewport', () => {
    assert.equal(isBehindLatest(scroller({ 6: 600 }), messages(30)), true);
    assert.equal(isBehindLatest(scroller({ 6: 601 }), messages(30)), true);
});

void test('hides the shortcut as soon as any of the newest 25 messages is visible', () => {
    assert.equal(isBehindLatest(scroller({ 6: 599 }), messages(30)), false);
    assert.equal(isBehindLatest(scroller({ 6: -1500 }), messages(30)), false);
});

void test('does not offer a scroll shortcut for fewer than 25 messages or missing rows', () => {
    assert.equal(isBehindLatest(scroller({ 1: 900 }), messages(24)), false);
    assert.equal(isBehindLatest(scroller({}), messages(30)), false);
    assert.equal(isBehindLatest(scroller({}), []), false);
});

void test('counts messages rather than org events or gaps in server ids', () => {
    const rows = messages(30).map((message) => ({ ...message, id: message.id * 10, client_id: `message-${message.id * 10}` }));
    rows.push({ id: 999, event: { type: 'joined' } } as unknown as Message);

    assert.equal(isBehindLatest(scroller({ 60: 601, 70: 599 }), rows), true);
});

void test('uses the actual viewport edge and bubble height instead of a fixed pixel distance', () => {
    assert.equal(isBehindLatest(scroller({ 6: 430 }, 420), messages(30)), true);
    assert.equal(isBehindLatest(scroller({ 6: 430 }, 650), messages(30)), false);
});

void test('offers the present when an image-only message is the newest-25 boundary', () => {
    const rows = messages(30);
    rows[5] = { ...rows[5], body: '', images: [{ id: 'photo' }] } as Message;

    assert.equal(isBehindLatest(scroller({ 6: 601 }, 600, {}), rows), true);
    assert.equal(isBehindLatest(scroller({ 6: 599 }, 600, {}), rows), false);
});

void test('keeps the shortcut hidden while a boundary image is visible above its text caption', () => {
    assert.equal(isBehindLatest(scroller({ 6: 599 }, 600, { 6: 650 }), messages(30)), false);
});
