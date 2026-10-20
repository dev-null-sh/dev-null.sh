import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { GraphCanvas } from '../../resources/js/components/graph-canvas';
import { GraphRail } from '../../resources/js/components/graph-rail';

const endDot = (markup: string): string | undefined =>
    markup.match(/<span[^>]*data-test="rail-end-dot"[^>]*>/)?.[0];

test('scales a fresh commit end dot in once its line has drawn itself in', () => {
    const dot = endDot(
        renderToStaticMarkup(
            <GraphRail lane="var(--lane-1)" endsWithDot isFresh />,
        ),
    );

    assert.ok(dot);
    assert.match(dot, /motion-safe:animate-node-in/);
    assert.match(dot, /animation-delay:900ms/);
});

test('leaves a fresh commit still when it is there as its canvas first draws, like a thread opened right after posting', () => {
    const markup = renderToStaticMarkup(
        <GraphCanvas>
            <GraphRail
                lane="var(--lane-1)"
                endsWithDot
                isFresh
                graph={{ id: 'commit', endId: 'commit:end' }}
            />
        </GraphCanvas>,
    );

    assert.doesNotMatch(markup, /animate-node-in/);
});

test('leaves the end dot of a commit already there still', () => {
    const dot = endDot(
        renderToStaticMarkup(<GraphRail lane="var(--lane-1)" endsWithDot />),
    );

    assert.ok(dot);
    assert.doesNotMatch(dot, /animate-node-in/);
});
