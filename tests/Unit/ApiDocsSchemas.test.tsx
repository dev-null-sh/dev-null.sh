/// <reference types="node" />

import assert from 'node:assert/strict';
import { act, createRef, useImperativeHandle } from 'react';
import { createRoot } from 'react-dom/client';
import { renderToString } from 'react-dom/server';
import { afterEach, test, vi } from 'vite-plus/test';
import {
    FIELDS_EVERYWHERE,
    hasSchemaTables,
    pickView,
    wantsSchemas,
    retryKeepingFocus,
    retryTarget,
    SchemaFailureAlert,
    SchemaOrFields,
} from '../../resources/js/components/api-docs/endpoint';
import { loadSchemas } from '../../resources/js/components/api-docs/schemas';
import { useIsLate } from '../../resources/js/hooks/use-is-late';

afterEach(() => {
    vi.unstubAllGlobals();
});

/**
 * The bare minimum React DOM needs to mount a component that renders nothing and commit its updates.
 */
function container(): Element {
    vi.stubGlobal('window', globalThis);
    vi.stubGlobal('IS_REACT_ACT_ENVIRONMENT', true);

    const view = {
        document: { activeElement: null },
        HTMLIFrameElement: class {},
    };
    const node = {
        nodeType: 1,
        nodeName: 'DIV',
        tagName: 'DIV',
        addEventListener: () => {},
        removeEventListener: () => {},
    };

    return {
        ...node,
        ownerDocument: { ...node, defaultView: view },
    } as unknown as Element;
}

const fields = [
    {
        name: 'body',
        type: 'string',
        required: true,
        description: null,
        enum: null,
        children: [],
    },
];

test('shows fields until the reader picks the schema view', () => {
    const html = renderToString(
        <SchemaOrFields
            fields={fields}
            view="fields"
            schemas={undefined}
            pick={(schemas) => schemas.body}
            onRetry={() => {}}
        />,
    );

    assert.match(html, />body</);
    assert.doesNotMatch(html, /Loading/);
});

test('shows the schema once it is loaded, and says so when it fails', () => {
    const schema = { code: '{}', html: '<span class="hl-value">{}</span>' };
    const pick = (schemas: { body: typeof schema | null }) => schemas.body;

    assert.match(
        renderToString(
            <SchemaOrFields
                fields={fields}
                view="schema"
                schemas={{ body: schema, responses: {} }}
                pick={pick}
                onRetry={() => {}}
            />,
        ),
        /<span class="hl-value">\{\}<\/span>/,
    );
    assert.match(
        renderToString(
            <SchemaOrFields
                fields={fields}
                view="schema"
                schemas="failed"
                pick={pick}
                onRetry={() => {}}
            />,
        ),
        /fatal: no schema/,
    );
});

test('keeps the fields in place while the schema loads, saying so only once it takes a while', () => {
    const html = renderToString(
        <SchemaOrFields
            fields={fields}
            view="schema"
            schemas="loading"
            pick={(schemas) => schemas.body}
            onRetry={() => {}}
        />,
    );

    assert.match(html, />body</);
    assert.match(html, /role="status"/);
    assert.doesNotMatch(html, /Loading the schema/);
});

test('tells a wait that takes a while from a quick one', () => {
    vi.useFakeTimers();
    const late = createRef<boolean>();
    const root = createRoot(container());

    function Probe({ isWaiting }: { isWaiting: boolean }) {
        const isLate = useIsLate(isWaiting);
        useImperativeHandle(late, () => isLate);

        return null;
    }

    act(() => root.render(<Probe isWaiting />));
    assert.equal(late.current, false);

    act(() => {
        vi.advanceTimersByTime(299);
    });
    assert.equal(late.current, false);

    act(() => {
        vi.advanceTimersByTime(1);
    });
    assert.equal(late.current, true);

    act(() => root.render(<Probe isWaiting={false} />));
    assert.equal(late.current, false);

    act(() => root.unmount());
    vi.useRealTimers();
});

test("asks for an endpoint's schemas once, however many places show them", async () => {
    const calls: string[] = [];
    vi.stubGlobal('fetch', async (url: string) => {
        calls.push(url);

        return { ok: true, json: async () => ({ body: null, responses: {} }) };
    });

    await Promise.all([
        loadSchemas('/docs/api/users/schemas/a'),
        loadSchemas('/docs/api/users/schemas/a'),
    ]);

    assert.deepEqual(calls, ['/docs/api/users/schemas/a']);
});

test('asks again for schemas that failed to load', async () => {
    const calls: string[] = [];
    let isUp = false;
    vi.stubGlobal('fetch', async (url: string) => {
        calls.push(url);

        return isUp
            ? { ok: true, json: async () => ({ body: null, responses: {} }) }
            : { ok: false, status: 503 };
    });

    await loadSchemas('/docs/api/users/schemas/b');
    isUp = true;
    await loadSchemas('/docs/api/users/schemas/b');
    await loadSchemas('/docs/api/users/schemas/b');

    assert.deepEqual(calls, [
        '/docs/api/users/schemas/b',
        '/docs/api/users/schemas/b',
    ]);
});

test('offers to try again over the fields when the schema failed to load, so nothing moves', () => {
    const html = renderToString(
        <SchemaOrFields
            fields={fields}
            view="schema"
            schemas="failed"
            pick={(schemas) => schemas.body}
            onRetry={() => {}}
        />,
    );

    assert.match(html, /<button[^>]*type="button"[^>]*>Try again<\/button>/);
    assert.match(html, />body</);
    assert.match(
        html,
        /<p class="[^"]*absolute inset-0[^"]*"[^>]*>(?:(?!<\/p>).)*max-w-full[^>]*>(?:(?!<\/p>).)*truncate[^>]*>fatal: no schema<\/span><button[^>]*shrink-0/s,
    );
    assert.doesNotMatch(html, /role="alert"/);
    assert.match(html, /<button[^>]*class="[^"]*min-h-6[^"]*"[^>]*>Try again/);
});

test('tells a screen reader once that an endpoint could not load its schema, its path read aloud', () => {
    const endpoint = { method: 'GET', path: '/users/{handle}' };

    assert.match(
        renderToString(
            <SchemaFailureAlert endpoint={endpoint} schemas="failed" />,
        ),
        /^<p role="alert" class="sr-only">The schema of GET \/users\/handle could not be loaded\.<\/p>$/,
    );
    assert.equal(
        renderToString(
            <SchemaFailureAlert endpoint={endpoint} schemas="loading" />,
        ),
        '',
    );
    assert.equal(
        renderToString(
            <SchemaFailureAlert endpoint={endpoint} schemas={undefined} />,
        ),
        '',
    );
});

test('hands focus back to the toggle of the part whose schema was tried again', () => {
    const toggle = { tagName: 'BUTTON' };
    const part = {
        querySelector: (selector: string) =>
            selector === 'button[aria-pressed="true"]' ? toggle : null,
    };
    const inPart = {
        closest: (selector: string) =>
            selector === '[data-part]' ? part : null,
    } as unknown as Element;
    const astray = { closest: () => null } as unknown as Element;

    assert.equal(retryTarget(inPart), toggle);
    assert.equal(retryTarget(astray), null);
});

test('asks for the schemas only of an endpoint that has fields to show them for', () => {
    const field = {
        name: 'body',
        type: 'string',
        required: true,
        description: null,
        enum: null,
        children: [],
    };
    const bare = { body: null, responses: [] };

    assert.equal(hasSchemaTables(bare), false);
    assert.equal(
        hasSchemaTables({
            ...bare,
            responses: [{ status: '204', description: '', fields: [] }],
        }),
        false,
    );
    assert.equal(
        hasSchemaTables({
            ...bare,
            body: { mediaType: 'application/json', fields: [field] },
        }),
        true,
    );
    assert.equal(
        hasSchemaTables({
            ...bare,
            responses: [{ status: '200', description: '', fields: [field] }],
        }),
        true,
    );
});

/**
 * A page where focus and the reader's scrolling can be followed, for retryKeepingFocus.
 */
function focusablePage({ deferFrames = false } = {}) {
    const calls: string[] = [];
    const listeners = new Map<string, Set<() => void>>();
    const frames: (() => void)[] = [];
    const page = { activeElement: null as unknown };
    vi.stubGlobal('requestAnimationFrame', (callback: () => void) => {
        calls.push('frame');

        if (deferFrames) {
            frames.push(callback);
        } else {
            callback();
        }
    });
    vi.stubGlobal('document', page);
    vi.stubGlobal('window', {
        addEventListener: (type: string, listener: () => void) =>
            listeners.set(
                type,
                (listeners.get(type) ?? new Set()).add(listener),
            ),
        removeEventListener: (type: string, listener: () => void) =>
            listeners.get(type)?.delete(listener),
    });
    const target = {
        focus: (options: FocusOptions) => {
            page.activeElement = target;
            calls.push(`focus ${JSON.stringify(options)}`);
        },
        scrollIntoView: (options: ScrollIntoViewOptions) =>
            calls.push(`scroll ${JSON.stringify(options)}`),
    } as unknown as HTMLElement;

    return {
        calls,
        target,
        page,
        fire: (type: string) =>
            listeners.get(type)?.forEach((listener) => listener()),
        runFrames: () => frames.splice(0).forEach((frame) => frame()),
        listening: () =>
            [...listeners.values()].reduce((count, set) => count + set.size, 0),
    };
}

test('keeps keyboard focus on the endpoint when trying a schema again, and in sight once it loads', async () => {
    const { calls, target, listening } = focusablePage();

    await retryKeepingFocus(async () => {
        calls.push('retry');
    }, target);
    await retryKeepingFocus(async () => {
        calls.push('retry');
    }, null);

    assert.deepEqual(calls, [
        'retry',
        'focus {"preventScroll":true}',
        'frame',
        'scroll {"block":"nearest"}',
        'retry',
    ]);
    assert.equal(listening(), 0);
});

test('leaves the page where the reader took it until the loaded schema is drawn, but not for the page scrolling itself', async () => {
    for (const input of ['wheel', 'touchmove', 'keydown']) {
        const { calls, target, fire, runFrames, listening } = focusablePage({
            deferFrames: true,
        });

        await retryKeepingFocus(async () => {}, target);
        fire(input);
        runFrames();

        assert.deepEqual(
            calls,
            ['focus {"preventScroll":true}', 'frame'],
            input,
        );
        assert.equal(listening(), 0);
    }

    const { calls, target, fire, runFrames } = focusablePage({
        deferFrames: true,
    });

    await retryKeepingFocus(async () => {}, target);
    fire('scroll');
    runFrames();

    assert.deepEqual(calls, [
        'focus {"preventScroll":true}',
        'frame',
        'scroll {"block":"nearest"}',
    ]);
});

test('leaves the page where the reader took it while the schema loaded', async () => {
    for (const input of ['wheel', 'touchmove', 'keydown', 'scroll']) {
        const { calls, target, fire, listening } = focusablePage();

        await retryKeepingFocus(async () => {
            fire(input);
        }, target);

        assert.deepEqual(calls, ['focus {"preventScroll":true}'], input);
        assert.equal(listening(), 0);
    }

    const { calls, target, page } = focusablePage();

    await retryKeepingFocus(async () => {
        await Promise.resolve();
        page.activeElement = null;
    }, target);

    assert.deepEqual(calls, ['focus {"preventScroll":true}']);
});

test('reads each part as its own toggle says, and asks for schemas only while one shows them', () => {
    const views = pickView(FIELDS_EVERYWHERE, 'responses', 'schema');

    assert.deepEqual(views, {
        body: 'fields',
        responses: 'schema',
        errors: 'fields',
    });
    assert.deepEqual(FIELDS_EVERYWHERE, {
        body: 'fields',
        responses: 'fields',
        errors: 'fields',
    });
    assert.equal(wantsSchemas(FIELDS_EVERYWHERE), false);
    assert.equal(wantsSchemas(views), true);
});
