import assert from 'node:assert/strict';
import { test } from 'node:test';
import { middleClickedPermalink } from '../../resources/js/lib/new-tab.ts';

type FakeElement = {
    tag: string;
    dataset: Record<string, string>;
    parent: FakeElement | null;
    closest: (selectors: string) => FakeElement | null;
    contains: (other: FakeElement) => boolean;
};

const matches = (element: FakeElement, selector: string): boolean => {
    const attribute = /^\[(.+)\]$/.exec(selector)?.[1];

    return attribute
        ? attribute.replace(/^data-/, '') in element.dataset
        : element.tag === selector;
};

function element(
    tag: string,
    parent: FakeElement | null = null,
    dataset: Record<string, string> = {},
): FakeElement {
    const self: FakeElement = {
        tag,
        dataset,
        parent,
        closest: (selectors) => {
            for (
                let node: FakeElement | null = self;
                node;
                node = node.parent
            ) {
                if (
                    selectors
                        .split(',')
                        .some((selector) => matches(node, selector.trim()))
                ) {
                    return node;
                }
            }

            return null;
        },
        contains: (other) => {
            for (
                let node: FakeElement | null = other;
                node;
                node = node.parent
            ) {
                if (node === self) {
                    return true;
                }
            }

            return false;
        },
    };

    return self;
}

const click = (button: number, target: FakeElement, card: FakeElement) =>
    middleClickedPermalink(
        button,
        target as unknown as Element,
        card as unknown as HTMLElement,
    );

const card = element('article', element('main'), {
    permalink: '/@octocat/commits/commit-1',
});
const body = element('p', element('div', card));

await test('a middle click on a commit opens it', () => {
    assert.equal(click(1, body, card), '/@octocat/commits/commit-1');
    assert.equal(click(1, card, card), '/@octocat/commits/commit-1');
});

await test('other buttons leave the commit alone', () => {
    assert.equal(click(0, body, card), null);
    assert.equal(click(2, body, card), null);
});

await test('links, buttons, and fields inside keep their own middle click', () => {
    for (const tag of ['a', 'button', 'input', 'textarea', 'video']) {
        const control = element(tag, element('div', card));

        assert.equal(click(1, control, card), null);
        assert.equal(click(1, element('img', control), card), null);
    }
});

await test('a reply opens itself, not the commit around it', () => {
    const reply = element('article', card, {
        permalink: '/@hubot/commits/reply-1',
    });
    const replyBody = element('p', reply);

    assert.equal(click(1, replyBody, reply), '/@hubot/commits/reply-1');
    assert.equal(click(1, replyBody, card), null);
});

await test('a link around the card does not stop it from opening', () => {
    const linked = element('article', element('a'), {
        permalink: '/@octocat/commits/commit-2',
    });

    assert.equal(
        click(1, element('p', linked), linked),
        '/@octocat/commits/commit-2',
    );
});
