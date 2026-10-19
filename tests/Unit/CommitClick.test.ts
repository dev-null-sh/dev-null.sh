import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    type CommitClick,
    clickedCommit,
} from '../../resources/js/lib/commit-click.ts';

type FakeElement = {
    matches: (selector: string) => boolean;
    parent: FakeElement | null;
    dataset: Record<string, string | undefined>;
    closest: (selector: string) => FakeElement | null;
    contains: (node: unknown) => boolean;
};

/**
 * Just enough of the DOM to walk up from the clicked element: each element matches the selectors it was given.
 */
function element(
    selectors: string[],
    parent: FakeElement | null = null,
    dataset: Record<string, string> = {},
): FakeElement {
    const self: FakeElement = {
        parent,
        dataset,
        matches: (selector) =>
            selector.split(', ').some((part) => selectors.includes(part)),
        closest: (selector) => {
            for (let node: FakeElement | null = self; node; node = node.parent) {
                if (node.matches(selector)) {
                    return node;
                }
            }

            return null;
        },
        contains: (node) => {
            for (
                let current = node as FakeElement | null;
                current;
                current = current.parent
            ) {
                if (current === self) {
                    return true;
                }
            }

            return false;
        },
    };

    return self;
}

const card = element(['[data-commit]'], null, {
    permalink: '/@octocat/commits/1',
});
const body = element(['p'], card);
const link = element(['a'], card);
const reply = element(['[data-reply]'], card, {
    permalink: '/@hubot/commits/2',
});
const replyBody = element(['p'], reply);

function click(
    target: FakeElement,
    overrides: Partial<CommitClick> = {},
): CommitClick {
    return {
        button: 0,
        metaKey: false,
        ctrlKey: false,
        defaultPrevented: false,
        target: target as unknown as EventTarget,
        currentTarget: card,
        ...overrides,
    };
}

await test('clicking anywhere on a commit opens it', () => {
    assert.deepEqual(clickedCommit(click(body), '', '/'), {
        url: '/@octocat/commits/1',
        newTab: false,
    });
    assert.deepEqual(clickedCommit(click(card), '', '/'), {
        url: '/@octocat/commits/1',
        newTab: false,
    });
});

await test('clicking a reply opens the reply, not the commit it sits under', () => {
    assert.equal(clickedCommit(click(replyBody), '', '/')?.url, '/@hubot/commits/2');
});

await test('middle and modified clicks open the commit in a new tab', () => {
    assert.equal(clickedCommit(click(body, { button: 1 }), '', '/')?.newTab, true);
    assert.equal(clickedCommit(click(body, { metaKey: true }), '', '/')?.newTab, true);
    assert.equal(clickedCommit(click(body, { ctrlKey: true }), '', '/')?.newTab, true);
});

await test('links and controls inside a commit keep their own click', () => {
    assert.equal(clickedCommit(click(link), '', '/'), null);
    assert.equal(clickedCommit(click(element(['button'], card)), '', '/'), null);
    assert.equal(clickedCommit(click(body, { defaultPrevented: true }), '', '/'), null);
});

await test('a commit rendered inside a dialog is still clickable', () => {
    const dialog = element(['[role="dialog"]']);
    const dialogCard = element(['[data-commit]'], dialog, {
        permalink: '/@octocat/commits/1',
    });
    const dialogBody = element(['p'], dialogCard);

    assert.equal(
        clickedCommit(click(dialogBody, { currentTarget: dialogCard }), '', '/')?.url,
        '/@octocat/commits/1',
    );
});

await test('selecting text, right clicks, and portals do not open the commit', () => {
    assert.equal(clickedCommit(click(body), 'hello', '/'), null);
    assert.equal(clickedCommit(click(body, { button: 2 }), '', '/'), null);
    assert.equal(clickedCommit(click(element(['p'])), '', '/'), null);
});

await test('the commit you are already on does not reopen', () => {
    assert.equal(clickedCommit(click(body), '', '/@octocat/commits/1'), null);
    assert.equal(
        clickedCommit(click(replyBody), '', '/@octocat/commits/1')?.url,
        '/@hubot/commits/2',
    );
});

await test('deleted commits have no permalink to open', () => {
    const tombstone = element(['[data-reply]'], card);

    assert.equal(clickedCommit(click(element(['p'], tombstone)), '', '/'), null);
});
