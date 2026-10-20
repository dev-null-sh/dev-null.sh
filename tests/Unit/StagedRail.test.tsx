import assert from 'node:assert/strict';
import { createInertiaApp } from '@inertiajs/react';
import { renderToStaticMarkup, renderToString } from 'react-dom/server';
import type { ReactElement } from 'react';
import { test } from 'vite-plus/test';
import {
    GraphRail,
    RailSegment,
} from '../../resources/js/components/graph-rail';

const stagedTail = (markup: string): string | undefined =>
    markup.match(/<span[^>]*data-test="rail-staged-tail"[^>]*>/)?.[0];

async function renderRail(
    rail: ReactElement,
    fadesLanes = true,
): Promise<string> {
    const result = await createInertiaApp({
        page: {
            component: 'test',
            props: {
                auth: { user: null },
                errors: {},
                dimsLanes: true,
                fadesLanes,
            },
            url: '/',
            version: null,
            clearHistory: false,
            encryptHistory: false,
            rescuedProps: [],
            rememberedState: {},
            flash: {},
        },
        resolve: () => () => rail,
        render: renderToString,
        setup: ({ App, props }) => <App {...props} />,
    });

    assert.ok(result);

    return result.body;
}

test('fades the staged rail from the draft grey into the next lane', async () => {
    const tail = stagedTail(
        await renderRail(
            <GraphRail
                lane="var(--lane-1)"
                nextLane="var(--lane-4)"
                variant="staged"
            />,
        ),
    );

    assert.ok(tail);
    assert.match(
        tail,
        /linear-gradient\(to bottom, var\(--rail\) 0%, var\(--rail\) 75%, var\(--lane-4\) 100%\)/,
    );
    assert.match(
        tail,
        /mask-image:repeating-linear-gradient\(to top, transparent 0 4px, #000 4px 8px\)/,
    );
});

test('keeps the staged rail grey when lane fades are off', async () => {
    const tail = stagedTail(
        await renderRail(
            <GraphRail
                lane="var(--lane-1)"
                nextLane="var(--lane-4)"
                variant="staged"
            />,
            false,
        ),
    );

    assert.ok(tail);
    assert.match(tail, /background-color:var\(--rail\)/);
    assert.doesNotMatch(tail, /(?<!repeating-)linear-gradient/);
});

test('keeps the staged rail grey when nothing follows it', async () => {
    const tail = stagedTail(
        await renderRail(<GraphRail lane="var(--lane-1)" variant="staged" />),
    );

    assert.ok(tail);
    assert.match(tail, /background-color:var\(--rail\)/);
    assert.doesNotMatch(tail, /(?<!repeating-)linear-gradient/);
});

test('carries a dashed rail through a segment in the given lane', () => {
    const markup = renderToStaticMarkup(
        <RailSegment lane="var(--lane-4)" isDashed />,
    );

    assert.match(markup, /background-color:var\(--lane-4\)/);
    assert.match(
        markup,
        /mask-image:repeating-linear-gradient\(to bottom, #000 0 4px, transparent 4px 8px\)/,
    );
});

test('keeps a plain segment solid', () => {
    const markup = renderToStaticMarkup(<RailSegment lane="var(--lane-4)" />);

    assert.match(markup, /background:var\(--lane-4\)/);
    assert.doesNotMatch(markup, /mask-image/);
});
