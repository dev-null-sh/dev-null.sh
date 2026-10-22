/// <reference types="node" />
import assert from 'node:assert/strict';
import { Link } from '@inertiajs/react';
import * as React from 'react';
import type { ReactElement, ReactNode } from 'react';
import { test, vi } from 'vite-plus/test';
import { PageTabs } from '../../resources/js/components/page-tabs';

type Element = ReactElement<Record<string, unknown>>;

const tabs = [
    { title: 'Commits', href: '/@nuno', isActive: true },
    { title: 'Replies', href: '/@nuno?tab=replies', isActive: false },
];

function links(node: ReactNode): Element[] {
    if (!React.isValidElement(node)) return [];
    const element = node as Element;
    return [
        ...(element.type === Link ? [element] : []),
        ...React.Children.toArray(element.props.children as ReactNode).flatMap(
            links,
        ),
    ];
}

test('animates the page between tabs when the page does not select them itself', () => {
    const rendered = links(PageTabs({ label: 'Profile feed', tabs }));

    assert.equal(rendered.length, 2);
    assert.ok(rendered.every((link) => link.props.viewTransition === true));

    for (const link of rendered) {
        assert.doesNotThrow(() => (link.props.onBefore as () => void)());
    }
});

test('selects the tab as its visit starts and leaves the animation to the page', () => {
    const onSelect = vi.fn();
    const rendered = links(PageTabs({ label: 'Profile feed', tabs, onSelect }));

    assert.ok(rendered.every((link) => link.props.viewTransition === false));

    (rendered[1].props.onBefore as () => void)();
    (rendered[0].props.onBefore as () => void)();

    assert.deepEqual(onSelect.mock.calls, [[1], [0]]);
});
