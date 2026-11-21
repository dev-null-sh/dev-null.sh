import assert from 'node:assert/strict';
import { test } from 'node:test';
import { commitActionForShortcut } from '../../resources/js/lib/commit-shortcuts.ts';

Object.defineProperty(globalThis, 'CSS', {
    configurable: true,
    value: { escape: (value: string) => value },
});

function card(
    name: string,
    keys: string[],
    clicks: string[],
    inert = false,
    focuses?: string[],
): Element {
    const element = {
        querySelector: (selector: string) => {
            const key = selector.match(/^button\[aria-keyshortcuts="(.+)"\]$/)?.[1];

            return key && keys.includes(key)
                ? {
                      click: () => clicks.push(`${name}:${key}`),
                      closest: () => (inert ? {} : null),
                  }
                : null;
        },
        querySelectorAll: (selector: string) =>
            selector === '[data-test="reply-composer"] textarea' && focuses
                ? [
                      {
                          tagName: 'TEXTAREA',
                          focus: () => focuses.push(name),
                          closest: (selector: string) =>
                              selector === '[data-reply], [data-commit]'
                                  ? element
                                  : inert
                                    ? {}
                                    : null,
                      },
                  ]
                : [],
    };

    return element as unknown as Element;
}

function press(key: string, active: Element | null, page: Element | null): boolean {
    const action = commitActionForShortcut(key, active, page);
    if (action?.tagName === 'TEXTAREA') {
        action.focus();
    } else {
        action?.click();
    }

    return action !== null;
}

test('reply uses the main commit when focus is outside any commit', () => {
    const clicks: string[] = [];

    assert.equal(press('r', null, card('main', ['r'], clicks)), true);
    assert.deepEqual(clicks, ['main:r']);
});

test('reply prefers the commit containing keyboard focus', () => {
    const clicks: string[] = [];

    assert.equal(
        press('r', card('reply', ['r'], clicks), card('main', ['r'], clicks)),
        true,
    );
    assert.deepEqual(clicks, ['reply:r']);
});

test('quote does not use the detail-page fallback', () => {
    const clicks: string[] = [];

    assert.equal(press('q', null, card('main', ['q'], clicks)), false);
    assert.deepEqual(clicks, []);
});

test('quote still works when a commit has keyboard focus', () => {
    const clicks: string[] = [];

    assert.equal(press('q', card('selected', ['q'], clicks), null), true);
    assert.deepEqual(clicks, ['selected:q']);
});

test('without any commit, reply does nothing', () => {
    assert.equal(press('r', null, null), false);
});

test('actions inside inert commits are not triggered', () => {
    const clicks: string[] = [];

    assert.equal(press('r', card('deleted', ['r'], clicks, true), null), false);
    assert.deepEqual(clicks, []);
});

test('reply focuses an already open composer without cancelling it', () => {
    const clicks: string[] = [];
    const focuses: string[] = [];
    const main = card('main', [], clicks, false, focuses);

    assert.equal(press('r', null, main), true);
    assert.deepEqual(focuses, ['main']);
    assert.deepEqual(clicks, []);
});

test('an open composer inside an inert card cannot receive focus', () => {
    const focuses: string[] = [];

    assert.equal(press('r', null, card('deleted', [], [], true, focuses)), false);
    assert.deepEqual(focuses, []);
});

test('a nested reply composer does not take the main commit shortcut', () => {
    const focuses: string[] = [];
    const reply = card('nested', [], [], false, focuses);
    const main = card('main', [], []);

    main.querySelectorAll = reply.querySelectorAll.bind(reply);

    assert.equal(press('r', null, main), false);
    assert.deepEqual(focuses, []);
});
