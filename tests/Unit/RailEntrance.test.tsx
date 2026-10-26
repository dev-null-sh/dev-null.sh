import assert from 'node:assert/strict';
import { renderToStaticMarkup } from 'react-dom/server';
import { test } from 'vite-plus/test';
import { GraphCanvas } from '../../resources/js/components/graph-canvas';
import {
    ChainedEntrance,
    GraphRail,
    RailEndDot,
} from '../../resources/js/components/graph-rail';

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

test('scales a fresh commit going on below another one in once the trunk has reached it, and its end dot after', () => {
    const markup = renderToStaticMarkup(
        <GraphRail
            lane="var(--lane-1)"
            previousLane="var(--lane-1)"
            endsWithDot
            isFresh
        />,
    );
    const node = markup
        .match(/<span[^>]*group\/rail-node[^>]*>/g)
        ?.find((tag) => !tag.includes('rail-end-dot'));

    assert.ok(node);
    assert.match(node, /animation-delay:300ms/);
    assert.match(endDot(markup) ?? '', /animation-delay:1200ms/);
    assert.match(endDot(markup) ?? '', /data-graph-trails/);
});

test('fades a fresh commit going on below another one in as the trunk draws itself to it', () => {
    const markup = renderToStaticMarkup(
        <ChainedEntrance isFresh previousLane="var(--lane-1)">
            body
        </ChainedEntrance>,
    );

    assert.match(markup, /motion-safe:animate-commit-in/);
    assert.match(markup, /animation-duration:300ms/);
});

test('leaves a commit still when it starts a thread, is already there, or is there as its canvas first draws', () => {
    for (const markup of [
        renderToStaticMarkup(<ChainedEntrance isFresh>body</ChainedEntrance>),
        renderToStaticMarkup(
            <ChainedEntrance previousLane="var(--lane-1)">
                body
            </ChainedEntrance>,
        ),
        renderToStaticMarkup(
            <GraphCanvas>
                <ChainedEntrance isFresh previousLane="var(--lane-1)">
                    body
                </ChainedEntrance>
            </GraphCanvas>,
        ),
    ]) {
        assert.doesNotMatch(markup, /animate-commit-in/);
    }
});

test('scales the end dot out, the way it scaled in, once the thread goes on below it', () => {
    const dot = endDot(renderToStaticMarkup(<RailEndDot isLeaving />));

    assert.ok(dot);
    assert.match(dot, /motion-safe:animate-node-out/);
    assert.match(dot, /animation-duration:300ms/);
    assert.doesNotMatch(dot, /animate-node-in/);
    assert.match(dot, /data-graph-leaves/);
});

test('leaves the end dot of a commit already there still', () => {
    const dot = endDot(
        renderToStaticMarkup(<GraphRail lane="var(--lane-1)" endsWithDot />),
    );

    assert.ok(dot);
    assert.doesNotMatch(dot, /animate-node-in/);
});
