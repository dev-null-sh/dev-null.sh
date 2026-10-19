import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import {
    GraphRail,
    RailSegment,
} from '../../resources/js/components/graph-rail';

const stagedTail = (markup: string): string | undefined =>
    markup.match(/<span[^>]*data-test="rail-staged-tail"[^>]*>/)?.[0];

test('keeps the staged rail grey', () => {
    const tail = stagedTail(
        renderToStaticMarkup(
            <GraphRail lane="var(--lane-1)" variant="staged" />,
        ),
    );

    assert.ok(tail);
    assert.match(tail, /background-color:var\(--rail\)/);
    assert.doesNotMatch(tail, /(?<!repeating-)linear-gradient/);
    assert.match(
        tail,
        /mask-image:repeating-linear-gradient\(to top, transparent 0 4px, #000 4px 8px\)/,
    );
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
