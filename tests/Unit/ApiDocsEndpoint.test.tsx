/// <reference types="node" />

import assert from 'node:assert/strict';
import { act, createRef, useImperativeHandle, useState } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import { createChoice } from '../../resources/js/components/api-docs/choice';
import {
    Endpoint,
    EndpointDetails,
    EndpointPath,
    MethodChip,
    SignatureChip,
    endpointAnchor,
    isSharedError,
    revealEndpoint,
    useEndpointOpen,
} from '../../resources/js/components/api-docs/endpoint';
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
import {
    FieldTable,
    SpecText,
} from '../../resources/js/components/api-docs/field-table';
import type { ApiDocsEndpoint } from '../../resources/js/types';

afterEach(() => {
    vi.unstubAllGlobals();
});

const endpoint: ApiDocsEndpoint = {
    id: 'api-user-follow.store',
    method: 'POST',
    path: '/users/{handle}/follow',
    summary: 'Follow them',
    description: null,
    signature: 'required',
    parameters: [],
    body: null,
    responses: [],
    examples: [],
};

test('shows the client picked last on every endpoint that has it', () => {
    const { container, storage } = browser('');
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
    const { container } = browser('');
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
    const { container, storage } = browser('');
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

test('shows only the active example where its height must follow it, as in a sticky column', () => {
    const html = renderToString(
        <ExampleTabs
            holdHeight={false}
            examples={[
                { label: 'curl', language: 'bash', code: 'curl', html: 'curl' },
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
    assert.doesNotMatch(panels[0] ?? '', /hidden|row-start-1/);
    assert.match(panels[1] ?? '', /hidden=""/);
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

test('tints a method by what it does, reading in green, writing in ochre, deleting in red', () => {
    assert.match(renderToString(<MethodChip method="GET" />), /bg-success\/10/);
    assert.match(
        renderToString(<MethodChip method="POST" />),
        /bg-warning\/10/,
    );
    assert.match(
        renderToString(<MethodChip method="PATCH" />),
        /bg-warning\/10/,
    );
    assert.match(
        renderToString(<MethodChip method="DELETE" />),
        /bg-destructive\/10/,
    );
});

test('tints a signed endpoint in ochre and leaves a public one plain', () => {
    assert.match(
        renderToString(<SignatureChip signature="required" />),
        /bg-warning\/10[^>]*>.*signed/s,
    );
    assert.doesNotMatch(
        renderToString(<SignatureChip signature="optional" />),
        /warning/,
    );
});

test('lists path and query parameters apart', () => {
    const field = {
        required: false,
        description: null,
        enum: null,
        children: [],
    };
    const html = renderToString(
        <EndpointDetails
            endpoint={{
                ...endpoint,
                parameters: [
                    {
                        ...field,
                        in: 'path',
                        name: 'handle',
                        type: 'string',
                        required: true,
                    },
                    { ...field, in: 'query', name: 'replies', type: 'boolean' },
                ],
            }}
            section="users"
        />,
    );

    assert.match(
        html,
        />path parameters<.*>handle<.*>query parameters<.*>replies</s,
    );
    assert.doesNotMatch(html, /path · |query · /);
});

test('lists what an endpoint answers apart from its errors', () => {
    const html = renderToString(
        <EndpointDetails
            endpoint={{
                ...endpoint,
                responses: [
                    {
                        status: '201',
                        description: 'The new follow',
                        fields: [],
                    },
                    {
                        status: '422',
                        description: 'Validation error',
                        fields: [],
                    },
                    {
                        status: '429',
                        description: 'Too many requests',
                        fields: [],
                    },
                ],
            }}
            section="users"
        />,
    );

    assert.match(html, />responses<.*>201<.*>errors<.*>422<.*>429</s);
    assert.doesNotMatch(html.split('>errors<')[0] ?? '', />422</);
});

test('marks each part that can show its schema, its toggle inside', () => {
    const field = {
        name: 'body',
        type: 'string',
        required: true,
        description: null,
        enum: null,
        children: [],
    };
    const html = renderToString(
        <EndpointDetails
            endpoint={{
                ...endpoint,
                body: { mediaType: 'application/json', fields: [field] },
                responses: [
                    { status: '201', description: '', fields: [field] },
                ],
            }}
            section="users"
        />,
    );
    const parts = html.split('data-part=""').slice(1);

    assert.equal(parts.length, 2);
    assert.ok(parts.every((part) => /aria-pressed="true"/.test(part)));
});

test('says nothing of errors an endpoint has none of', () => {
    assert.doesNotMatch(
        renderToString(
            <EndpointDetails
                endpoint={{
                    ...endpoint,
                    responses: [{ status: '200', description: '', fields: [] }],
                }}
                section="users"
            />,
        ),
        />errors</,
    );
});

test('mutes the parameters of a path', () => {
    assert.equal(
        renderToString(<EndpointPath path="/users/{handle}/follow" />),
        '<span class="font-mono text-[13px] [overflow-wrap:anywhere]">/users/<span class="text-muted-foreground">{handle}</span>/follow</span>',
    );
});

test('folds an endpoint to its method, path, summary and whether it is signed', () => {
    const html = renderToString(
        <Endpoint endpoint={endpoint} section="users" />,
    );

    assert.match(html, /id="endpoint-api-user-follow-store"/);
    assert.match(html, /aria-expanded="false"/);
    assert.match(html, />POST</);
    assert.match(html, />Follow them</);
    assert.match(html, />signed</);
    assert.equal(endpointAnchor(endpoint), 'endpoint-api-user-follow-store');
});

/**
 * A browser window as far as an endpoint's hash goes, and the bare minimum React DOM needs to mount a component that
 * renders nothing.
 */
function browser(hash: string) {
    const scrolled: string[] = [];
    const storage = new Map<string, string>();
    const view = Object.assign(new EventTarget(), {
        location: { hash },
        event: undefined,
        localStorage: {
            getItem: (key: string) => storage.get(key) ?? null,
            setItem: (key: string, value: string) => storage.set(key, value),
        },
    });
    vi.stubGlobal('window', view);
    vi.stubGlobal('document', {
        getElementById: (id: string) => ({
            scrollIntoView: () => scrolled.push(id),
        }),
    });
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

    return {
        scrolled,
        storage,
        container,
        go: (next: string) =>
            act(() => {
                view.location.hash = next;
                view.dispatchEvent(new Event('hashchange'));
            }),
    };
}

type Fold = { isOpen: boolean; setIsOpen: (open: boolean) => void };

function mountFold(anchor: string, container: Element) {
    const fold = createRef<Fold>();

    function Probe() {
        const [isOpen, setIsOpen] = useEndpointOpen(anchor);
        useImperativeHandle(fold, () => ({ isOpen, setIsOpen }));

        return null;
    }

    const root = createRoot(container);
    act(() => root.render(<Probe />));

    return {
        fold,
        unmount: () => act(() => root.unmount()),
    };
}

test('opens the endpoint the hash points at, and leaves the others folded', () => {
    const { container } = browser('#endpoint-a');
    const linked = mountFold('endpoint-a', container);
    const other = mountFold('endpoint-b', container);

    assert.equal(linked.fold.current?.isOpen, true);
    assert.equal(other.fold.current?.isOpen, false);

    linked.unmount();
    other.unmount();
    vi.unstubAllGlobals();
});

test('opens an endpoint folded by hand again when a link points at it anew', () => {
    const { container, go } = browser('#endpoint-a');
    const { fold, unmount } = mountFold('endpoint-a', container);

    act(() => fold.current?.setIsOpen(false));
    assert.equal(fold.current?.isOpen, false);

    go('#endpoint-b');
    assert.equal(fold.current?.isOpen, false);

    go('#endpoint-a');
    assert.equal(fold.current?.isOpen, true);

    unmount();
    vi.unstubAllGlobals();
});

test('keeps an endpoint a link opened open when the hash moves on, so what follows never moves', () => {
    const { container, go } = browser('#endpoint-a');
    const { fold, unmount } = mountFold('endpoint-a', container);

    assert.equal(fold.current?.isOpen, true);

    go('#endpoint-b');
    assert.equal(fold.current?.isOpen, true);

    unmount();
    vi.unstubAllGlobals();
});

test('opens and scrolls to an endpoint picked again while the hash already points at it', () => {
    const { container, scrolled } = browser('#endpoint-a');
    const { fold, unmount } = mountFold('endpoint-a', container);

    act(() => fold.current?.setIsOpen(false));
    act(() => revealEndpoint('endpoint-a'));

    assert.equal(fold.current?.isOpen, true);
    assert.deepEqual(scrolled, ['endpoint-a']);

    unmount();
    vi.unstubAllGlobals();
});

test('leaves it to the hash to open and scroll to an endpoint it does not point at yet', () => {
    const { scrolled } = browser('#endpoint-b');

    revealEndpoint('endpoint-a');

    assert.equal(window.location.hash, 'endpoint-a');
    assert.deepEqual(scrolled, []);

    vi.unstubAllGlobals();
});

test('says what a field is, whether it is required, and folds what it holds', () => {
    const html = renderToString(
        <FieldTable
            fields={[
                {
                    name: 'data',
                    type: 'array of Commit',
                    required: true,
                    description: null,
                    enum: null,
                    children: [
                        {
                            name: 'id',
                            type: 'string',
                            required: true,
                            description: null,
                            enum: null,
                            children: [],
                        },
                        {
                            name: 'status',
                            type: 'CommitStatus',
                            required: true,
                            description: null,
                            enum: ['pending', 'approved'],
                            children: [],
                        },
                    ],
                },
            ]}
        />,
    );

    assert.match(html, />data<.*>array of Commit<.*>required</s);
    assert.match(html, /Show<!-- --> <!-- -->2<!-- --> <!-- -->fields/);
    assert.doesNotMatch(html, />status</);
});

test('leaves the errors every endpoint shares to the Errors guide', () => {
    assert.equal(
        isSharedError({
            status: '422',
            description: 'Validation error',
            fields: [],
        }),
        true,
    );
    assert.equal(
        isSharedError({
            status: '409',
            description: '',
            fields: [
                {
                    name: 'message',
                    type: 'string',
                    required: true,
                    description: null,
                    enum: ['Unable to claim this organization.'],
                    children: [],
                },
            ],
        }),
        false,
    );
    assert.equal(
        isSharedError({ status: '204', description: '', fields: [] }),
        false,
    );
});

test('tints a required field in ochre, as something the caller has to give', () => {
    const html = renderToString(
        <FieldTable
            fields={[
                {
                    name: 'body',
                    type: 'string',
                    required: true,
                    description: null,
                    enum: null,
                    children: [],
                },
            ]}
        />,
    );

    assert.match(html, /bg-warning\/10[^>]*>required</);
});

test('sets what the spec wraps in backticks as code', () => {
    assert.match(
        renderToString(<SpecText text="Paginated set of `CommitResource`" />),
        /Paginated set of <code[^>]*>CommitResource<\/code>/,
    );
});
