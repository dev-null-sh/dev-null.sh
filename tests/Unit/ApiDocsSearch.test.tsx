/// <reference types="node" />

import assert from 'node:assert/strict';
import { act, createRef, useImperativeHandle } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import {
    SearchPanel,
    focusResult,
    goToResult,
    handOffFocus,
    isSearchShortcut,
    moveActive,
    useSearchQuery,
} from '../../resources/js/components/api-docs/endpoint-search';
import { searchApiDocs } from '../../resources/js/components/api-docs/search';
import type {
    ApiDocsIndexEntry,
    ApiDocsSection,
} from '../../resources/js/types';

const sections: ApiDocsSection[] = [
    {
        slug: 'authentication',
        title: 'Authentication',
        description: 'Reading as anyone, or signing requests as someone',
        kind: 'guide',
    },
    {
        slug: 'users',
        title: 'Users',
        description: 'Profiles',
        kind: 'reference',
    },
    {
        slug: 'commits',
        title: 'Commits',
        description: 'Commits',
        kind: 'reference',
    },
];

const index: ApiDocsIndexEntry[] = [
    {
        id: 'api-user.show',
        method: 'GET',
        path: '/users/{handle}',
        summary: 'A profile as it shows on the web',
        section: 'users',
    },
    {
        id: 'api-user-follow.store',
        method: 'POST',
        path: '/users/{handle}/follow',
        summary: 'Follow them',
        section: 'users',
    },
    {
        id: 'api-commit.store',
        method: 'POST',
        path: '/commits',
        summary: 'Commit, reply or quote, as users do on the web',
        section: 'commits',
    },
    {
        id: 'api-commit-star.store',
        method: 'POST',
        path: '/commits/{commit}/star',
        summary: 'Star it',
        section: 'commits',
    },
];

function ids(query: string): string[] {
    return searchApiDocs(query, sections, index).map((result) => result.id);
}

test('lists every guide and endpoint before anything is typed', () => {
    assert.deepEqual(ids(''), [
        'authentication',
        'api-user.show',
        'api-user-follow.store',
        'api-commit.store',
        'api-commit-star.store',
    ]);
});

test('finds what holds every word typed, in any order and case', () => {
    assert.deepEqual(ids('post STAR'), ['api-commit-star.store']);
    assert.deepEqual(ids('follow users'), ['api-user-follow.store']);
    assert.deepEqual(ids('sign'), ['authentication']);
    assert.deepEqual(ids('webhooks'), []);
});

test('puts what matches by its method, path or group before what its summary mentions', () => {
    assert.deepEqual(ids('users'), [
        'api-user.show',
        'api-user-follow.store',
        'api-commit.store',
    ]);
});

afterEach(() => {
    vi.unstubAllGlobals();
});

class Tag {
    isContentEditable = false;

    constructor(public tagName: string) {}
}

function key(
    name: string,
    modifiers: Partial<{
        metaKey: boolean;
        ctrlKey: boolean;
        altKey: boolean;
        target: EventTarget | null;
    }> = {},
) {
    return {
        key: name,
        metaKey: false,
        ctrlKey: false,
        altKey: false,
        target: new Tag('BODY') as unknown as EventTarget,
        ...modifiers,
    };
}

test('opens from ⌘K, Ctrl+K or / anywhere in the docs', () => {
    vi.stubGlobal('HTMLElement', Tag);

    assert.equal(isSearchShortcut(key('k', { metaKey: true })), true);
    assert.equal(isSearchShortcut(key('K', { ctrlKey: true })), true);
    assert.equal(isSearchShortcut(key('/')), true);
    assert.equal(isSearchShortcut(key('k')), false);
    assert.equal(isSearchShortcut(key('/', { altKey: true })), false);
});

test('leaves / to whoever is typing', () => {
    vi.stubGlobal('HTMLElement', Tag);

    for (const tagName of ['INPUT', 'TEXTAREA', 'SELECT']) {
        assert.equal(
            isSearchShortcut(
                key('/', {
                    target: new Tag(tagName) as unknown as EventTarget,
                }),
            ),
            false,
        );
    }

    assert.equal(
        isSearchShortcut(
            key('k', {
                metaKey: true,
                target: new Tag('INPUT') as unknown as EventTarget,
            }),
        ),
        true,
    );
});

test('moves through the results with the arrow keys, round the ends', () => {
    assert.equal(moveActive(0, 'ArrowDown', 3), 1);
    assert.equal(moveActive(2, 'ArrowDown', 3), 0);
    assert.equal(moveActive(0, 'ArrowUp', 3), 2);
    assert.equal(moveActive(7, 'ArrowDown', 3), 0);
    assert.equal(moveActive(0, 'Enter', 3), null);
    assert.equal(moveActive(0, 'ArrowDown', 0), null);
});

function stubTarget() {
    const focused: { selector: string; options: unknown }[] = [];
    vi.stubGlobal('document', {
        querySelector: (selector: string) => ({
            focus: (options: unknown) => focused.push({ selector, options }),
        }),
    });

    return focused;
}

test('keeps the search open, as typed, until the page picked has arrived', () => {
    const calls: string[] = [];
    const visits: { url: string; onSuccess: () => void }[] = [];
    const visit = (url: string, options: { onSuccess: () => void }) => {
        visits.push({ url, ...options });
    };
    const reveal = (anchor: string) => calls.push(`reveal ${anchor}`);
    const close = (isHere: boolean) => calls.push(`close ${isHere}`);
    const frames: (() => void)[] = [];
    const focused = stubTarget();
    vi.stubGlobal('window', {
        requestAnimationFrame: (frame: () => void) => frames.push(frame),
    });
    const [elsewhere] = searchApiDocs('post star', sections, index);
    const [guide] = searchApiDocs('sign', sections, index);

    assert.equal(goToResult(elsewhere, 'users', close, visit, reveal), false);
    assert.equal(goToResult(guide, 'users', close, visit, reveal), false);
    assert.deepEqual(calls, []);
    assert.deepEqual(
        visits.map(({ url }) => url),
        [
            '/docs/api/commits#endpoint-api-commit-star-store',
            '/docs/api/authentication',
        ],
    );

    assert.equal(frames.length, 0);

    visits[0].onSuccess();
    assert.deepEqual(calls, ['close false']);
    assert.deepEqual(focused, []);

    frames.forEach((frame) => frame());
    assert.deepEqual(
        focused.map(({ selector }) => selector),
        ['#endpoint-api-commit-star-store > button'],
    );

    assert.equal(goToResult(elsewhere, 'commits', close, visit, reveal), true);
    assert.deepEqual(calls, [
        'close false',
        'close true',
        'reveal endpoint-api-commit-star-store',
    ]);
});

test('hands focus back to the Search button when the search closes without a pick', () => {
    const focused = stubTarget();
    const returned: string[] = [];
    const [result] = searchApiDocs('post star', sections, index);

    handOffFocus(null, () => returned.push('search button'));
    handOffFocus({ result, isHere: false }, () =>
        returned.push('search button'),
    );
    handOffFocus({ result, isHere: true }, () =>
        returned.push('search button'),
    );

    assert.deepEqual(returned, ['search button']);
    assert.deepEqual(
        focused.map(({ selector }) => selector),
        ['#endpoint-api-commit-star-store > button'],
    );
});

test('hands focus to the toggle of the endpoint picked', () => {
    const focused = stubTarget();

    focusResult(searchApiDocs('post star', sections, index)[0]);

    assert.deepEqual(focused, [
        {
            selector: '#endpoint-api-commit-star-store > button',
            options: { preventScroll: true },
        },
    ]);
});

test('hands focus to the first section of the guide picked', () => {
    const focused = stubTarget();

    focusResult(searchApiDocs('sign', sections, index)[0]);

    assert.deepEqual(focused, [
        { selector: 'main article h2 a', options: { preventScroll: true } },
    ]);
});

test('keeps what was typed while the search closes, and starts afresh when it opens again', () => {
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);
    const search = createRef<ReturnType<typeof useSearchQuery>>();

    function Probe({ isOpen }: { isOpen: boolean }) {
        const state = useSearchQuery(isOpen);
        useImperativeHandle(search, () => state);

        return null;
    }

    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };
    const root = createRoot({
        ...node,
        ownerDocument: {
            ...node,
            defaultView: {
                document: { activeElement: null },
                HTMLIFrameElement: class {},
            },
        },
    } as unknown as Element);

    act(() => root.render(<Probe isOpen />));
    act(() => search.current?.type('star'));
    act(() => search.current?.setActiveIndex(2));
    act(() => root.render(<Probe isOpen={false} />));

    assert.equal(search.current?.query, 'star');
    assert.equal(search.current?.activeIndex, 2);

    act(() => root.render(<Probe isOpen />));

    assert.equal(search.current?.query, '');
    assert.equal(search.current?.activeIndex, 0);

    act(() => root.unmount());
});

function panel(query: string): string {
    return renderToString(
        <SearchPanel
            query={query}
            results={searchApiDocs(query, sections, index)}
            activeIndex={0}
            onType={() => {}}
            onActivate={() => {}}
            onPick={() => {}}
        />,
    );
}

test('names its field and hides the icon beside it', () => {
    const html = panel('');

    assert.match(html, /<input[^>]*aria-label="Search the API docs"/);
    assert.match(html, /<svg[^>]*lucide-search[^>]*aria-hidden="true"/);
});

test('points the field at its results only while there are some', () => {
    assert.match(
        panel('star'),
        /<input[^>]*aria-controls="api-docs-search-results"/,
    );
    assert.match(panel('star'), /<ul id="api-docs-search-results"/);

    assert.doesNotMatch(panel('webhooks'), /aria-controls/);
    assert.match(
        panel('webhooks'),
        /fatal: nothing matches &#x27;<!-- -->webhooks<!-- -->&#x27;/,
    );
});
