/// <reference types="node" />

import assert from 'node:assert/strict';
import { act, createRef, useImperativeHandle, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import { createChoice } from '../../resources/js/components/api-docs/choice';
import {
    activeExample,
    chooseExample,
    useChosenExample,
} from '../../resources/js/components/api-docs/example-choice';
import {
    ExampleTabs,
    moveToExample,
    tabKeyTarget,
} from '../../resources/js/components/api-docs/example-tabs';

afterEach(() => {
    vi.unstubAllGlobals();
});

test('shows the client picked last on every endpoint that has it', () => {
    const { container, storage } = browser();
    const chosen = createRef<{ label: string | null }>();

    function Probe() {
        const label = useChosenExample();
        useImperativeHandle(chosen, () => ({ label }));

        return null;
    }

    const root = createRoot(container);
    act(() => root.render(<Probe />));
    const label = () => chosen.current?.label ?? null;

    const labels = ['curl', 'Laravel', 'fetch'];

    assert.equal(activeExample(labels, label()), 0);

    act(() => chooseExample('Laravel'));

    assert.equal(label(), 'Laravel');
    assert.equal(storage.get('api-docs:example'), 'Laravel');
    assert.equal(activeExample(labels, label()), 1);
    assert.equal(activeExample(['curl', 'fetch'], label()), 0);

    act(() => root.unmount());
    vi.unstubAllGlobals();
});

function mountChoice(key: string, container: Element) {
    const choice = createChoice(key);
    const chosen = createRef<{
        value: string | null;
        rerender: () => void;
    }>();

    function Probe() {
        const value = choice.useChosen();
        const [, setRenders] = useState(0);
        useImperativeHandle(chosen, () => ({
            value,
            rerender: () => setRenders((renders) => renders + 1),
        }));

        return null;
    }

    const root = createRoot(container);
    act(() => root.render(<Probe />));

    return {
        value: () => chosen.current?.value ?? null,
        rerender: () => act(() => chosen.current?.rerender()),
        unmount: () => act(() => root.unmount()),
    };
}

test('reads a choice from the browser once, even when nothing was picked', () => {
    const { container } = browser();
    const getItem = vi.spyOn(window.localStorage, 'getItem');
    const choice = mountChoice('api-docs:test-miss', container);

    choice.rerender();
    choice.rerender();

    assert.equal(choice.value(), null);
    assert.equal(getItem.mock.calls.length, 1);

    choice.unmount();
    vi.unstubAllGlobals();
});

test('forgets a choice when another tab clears the storage', () => {
    const { container, storage } = browser();
    storage.set('api-docs:test-clear', 'schema');
    const choice = mountChoice('api-docs:test-clear', container);

    assert.equal(choice.value(), 'schema');

    storage.clear();
    act(() => {
        window.dispatchEvent(
            Object.assign(new Event('storage'), { key: null, newValue: null }),
        );
    });

    assert.equal(choice.value(), null);

    choice.unmount();
    vi.unstubAllGlobals();
});

test('shows the first example, the others a tab away', () => {
    const html = renderToString(
        <ExampleTabs
            examples={[
                {
                    label: 'curl',
                    language: 'bash',
                    code: 'curl …',
                    html: 'curl <span class="hl-generic">…</span>',
                },
                {
                    label: 'fetch',
                    language: 'javascript',
                    code: 'fetch()',
                    html: 'fetch()',
                },
            ]}
        />,
    );

    assert.match(html, /role="tablist"/);
    assert.match(html, /role="tab" aria-selected="true"[^>]*>curl</);
    assert.match(
        html,
        /role="tab" aria-selected="false"[^>]*tabindex="-1"[^>]*>fetch</,
    );
    assert.match(html, /curl <span class="hl-generic">…<\/span>/);
});

test('underlines the active example tab like the page tabs, with an underline that slides', () => {
    const html = renderToString(
        <ExampleTabs
            examples={[
                { label: 'curl', language: 'bash', code: '', html: '' },
                { label: 'fetch', language: 'javascript', code: '', html: '' },
            ]}
        />,
    );
    const tabs = html.match(/<button[^>]*role="tab".*?<\/button>/g) ?? [];

    assert.equal(tabs.length, 2);
    assert.ok(tabs.every((tab) => /text-\[15px\]/.test(tab)));
    assert.match(
        tabs[0] ?? '',
        /<span aria-hidden="true" class="[^"]*\[view-transition-class:tab-indicator\][^"]*" style="--transition-name:example-tab-[\w-]+"/,
    );
    assert.doesNotMatch(tabs[1] ?? '', /tab-indicator/);
});

test('keeps every example in the same place, the hidden ones holding the height of the tallest', () => {
    const html = renderToString(
        <ExampleTabs
            examples={[
                {
                    label: 'curl',
                    language: 'bash',
                    code: 'curl',
                    html: 'curl',
                },
                {
                    label: 'fetch',
                    language: 'javascript',
                    code: 'fetch()',
                    html: 'fetch()',
                },
            ]}
        />,
    );
    const panels = html.match(/<div[^>]*role="tabpanel"[^>]*>/g) ?? [];

    assert.equal(panels.length, 2);
    assert.ok(panels.every((panel) => /col-start-1 row-start-1/.test(panel)));
    assert.doesNotMatch(panels[0] ?? '', /inert|aria-hidden|invisible/);
    assert.match(panels[1] ?? '', /inert=""/);
    assert.match(panels[1] ?? '', /aria-hidden="true"/);
    assert.match(panels[1] ?? '', /invisible/);
    assert.match(html, />fetch\(\)</);
});

test('moves between example tabs with the arrow keys, round the ends, and to the ends with Home and End', () => {
    assert.equal(tabKeyTarget('ArrowRight', 0, 3), 1);
    assert.equal(tabKeyTarget('ArrowRight', 2, 3), 0);
    assert.equal(tabKeyTarget('ArrowLeft', 0, 3), 2);
    assert.equal(tabKeyTarget('Home', 2, 3), 0);
    assert.equal(tabKeyTarget('End', 0, 3), 2);
    assert.equal(tabKeyTarget('Enter', 0, 3), null);
});

test('picks the example a key moves to before focus moves to it, so the focused tab is the selected one', () => {
    const calls: string[] = [];
    const pick = (label: string) => calls.push(`pick ${label}`);
    const focus = (index: number) => calls.push(`focus ${index}`);

    assert.equal(
        moveToExample('ArrowRight', 0, ['curl', 'Laravel'], pick, focus),
        true,
    );
    assert.equal(
        moveToExample('Enter', 0, ['curl', 'Laravel'], pick, focus),
        false,
    );
    assert.deepEqual(calls, ['pick Laravel', 'focus 1']);
});

test('lets the keyboard reach the example shown', () => {
    const html = renderToString(
        <ExampleTabs
            examples={[
                {
                    label: 'curl',
                    language: 'bash',
                    code: 'curl …',
                    html: 'curl …',
                },
            ]}
        />,
    );

    assert.match(html, /role="tabpanel"[^>]*tabindex="0"/);
});

/**
 * A browser window as far as a stored choice goes, and the bare minimum React DOM needs to mount a component that
 * renders nothing.
 */
function browser() {
    const storage = new Map<string, string>();
    const view = Object.assign(new EventTarget(), {
        event: undefined,
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
        },
    });
    vi.stubGlobal('window', view);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };
    const container = {
        ...node,
        ownerDocument: {
            ...node,
            defaultView: {
                document: { activeElement: null },
                HTMLIFrameElement: class {},
            },
        },
    } as unknown as Element;

    return { storage, container };
}
