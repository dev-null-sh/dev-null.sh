/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { afterAll, beforeEach, test, vi } from 'vite-plus/test';

type Tab = 'commits' | 'replies';

let cells: unknown[] = [];
let cursor = 0;
let isDirty = false;

// Renders the hook like React does: state lives in cells between renders, and a render that updates state runs again.
vi.doMock('react', () => ({
    ...React,
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in cells)) {
            cells[index] = initial;
        }

        return [
            cells[index],
            (next: unknown) => {
                cells[index] = next;
                isDirty = true;
            },
        ];
    },
}));

const { useSelectedTab } =
    await import('../../resources/js/hooks/use-selected-tab');

function render(tab: Tab): [Tab, (tab: Tab) => void] {
    let result: [Tab, (tab: Tab) => void];

    do {
        isDirty = false;
        cursor = 0;
        result = useSelectedTab(tab);
    } while (isDirty);

    return result;
}

beforeEach(() => {
    cells = [];
});

afterAll(() => {
    vi.doUnmock('react');
});

test('starts on the tab the page loaded', () => {
    assert.equal(render('commits')[0], 'commits');
});

test('shows a tapped tab as selected while the page still has the other one', () => {
    const [, select] = render('commits');

    select('replies');

    assert.equal(render('commits')[0], 'replies');
});

test('keeps the tapped tab once the page lands on it', () => {
    render('commits')[1]('replies');
    render('commits');

    assert.equal(render('replies')[0], 'replies');
});

test('follows the page to a tab it lands on without a tap, like back and forward', () => {
    render('commits')[1]('replies');
    render('replies');

    assert.equal(render('commits')[0], 'commits');
});

test('settles on the last tapped tab when tapping back before the first one loads', () => {
    const [, select] = render('commits');

    select('replies');
    select('commits');

    assert.equal(render('commits')[0], 'commits');
});
