import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    bridgeDuration,
    drawnStretch,
    entranceDelay,
    entranceDraw,
    graphEdges,
    isOnScreen,
    leavingOf,
    nodeIdentity,
    nodeStartsIn,
    sameNodes,
    scopedNodeId,
} from '../../resources/js/lib/graph.ts';
import type { GraphNode } from '../../resources/js/lib/graph.ts';

const node = (
    overrides: Partial<GraphNode> & Pick<GraphNode, 'id'>,
): GraphNode => ({
    x: 32,
    y: 0,
    radius: 24,
    lane: 'var(--lane-1)',
    ...overrides,
});

void test('joins a node to the one before it on the same trunk with a straight solid line', () => {
    const [edge] = graphEdges([
        node({ id: 'a', y: 40 }),
        node({ id: 'b', y: 200, after: 'a', lane: 'var(--lane-2)' }),
    ]);

    assert.equal(edge.d, 'M 32 40 V 200');
    assert.equal(edge.dashed, false);
    assert.equal(edge.lane, 'var(--lane-1)');
    assert.deepEqual(edge.start, { x: 32, y: 40 });
    assert.deepEqual(edge.end, { x: 32, y: 200 });
});

void test('draws a line in the lane of the node it leaves, end to end, as the rail did', () => {
    const edges = graphEdges([
        node({ id: 'root', y: 40, lane: 'var(--lane-1)' }),
        node({
            id: 'reply',
            x: 100,
            y: 160,
            parent: 'root',
            lane: 'var(--lane-2)',
        }),
        node({
            id: 'sibling',
            x: 100,
            y: 300,
            parent: 'root',
            lane: 'var(--lane-3)',
        }),
        node({
            id: 'nested',
            x: 160,
            y: 400,
            parent: 'sibling',
            lane: 'var(--lane-4)',
        }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.id, edge.lane]),
        [
            ['branch:reply', 'var(--lane-1)'],
            ['branch:sibling', 'var(--lane-1)'],
            ['branch:nested', 'var(--lane-3)'],
        ],
    );
    assert.ok(edges.every((edge) => !('toLane' in edge)));
});

void test('dashes a trunk that a dashed node asks for', () => {
    const [edge] = graphEdges([
        node({ id: 'a', y: 40 }),
        node({ id: 'b', y: 200, after: 'a', dashed: true }),
    ]);

    assert.equal(edge.dashed, true);
});

void test('branches a first reply off its parent with one dashed elbow', () => {
    const [edge] = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, radius: 18, parent: 'parent' }),
    ]);

    assert.equal(edge.d, 'M 32 40 V 148 Q 32 160 44 160 H 100');
    assert.equal(edge.dashed, true);
    assert.deepEqual(edge.end, { x: 100, y: 160 });
});

void test('branches off the trunk at the reply instead of doubling it when the trunk goes on below', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'sibling', y: 400, after: 'parent', dashed: true }),
    ]);
    const branch = edges.find((edge) => edge.id === 'branch:reply');

    assert.ok(branch);
    assert.equal(branch.d, 'M 32 148 Q 32 160 44 160 H 100');
});

void test('branches every reply off its parent, each one from where the reply above it turns', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'first', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'second', x: 100, y: 300, parent: 'parent' }),
        node({ id: 'third', x: 100, y: 420, parent: 'parent' }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.id, edge.d]),
        [
            ['branch:first', 'M 32 40 V 148 Q 32 160 44 160 H 100'],
            ['branch:second', 'M 32 148 V 288 Q 32 300 44 300 H 100'],
            ['branch:third', 'M 32 288 V 408 Q 32 420 44 420 H 100'],
        ],
    );
    assert.deepEqual(edges[1].start, { x: 32, y: 148 });
    assert.equal(edges[1].drop, 140);
});

void test("starts each branch's dashes where the column above left them, so the column reads as one line", () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'first', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'second', x: 100, y: 300, parent: 'parent' }),
        node({ id: 'more', y: 380, parent: 'parent' }),
        node({ id: 'next', y: 500, after: 'more' }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.id, edge.dashOffset]),
        [
            ['branch:first', 0],
            ['branch:second', 108],
            ['branch:more', 248],
            ['more->next', 0],
        ],
    );
});

void test('runs straight down to a node on the parent itself, like a "more replies" dot', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'more', y: 240, parent: 'parent' }),
    ]);
    const toDot = edges.find((edge) => edge.id === 'branch:more');

    assert.ok(toDot);
    assert.equal(toDot.d, 'M 32 148 V 240');
    assert.equal(toDot.drop, 92);
});

void test('branches each reply off the trunk at its own corner when the trunk goes on below them all', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'first', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'second', x: 100, y: 300, parent: 'parent' }),
        node({ id: 'next', y: 500, after: 'parent' }),
    ]);

    assert.deepEqual(
        edges.filter((edge) => edge.to !== 'next').map((edge) => edge.d),
        ['M 32 148 Q 32 160 44 160 H 100', 'M 32 288 Q 32 300 44 300 H 100'],
    );
});

void test('keeps the replies their dashed column while the trunk going on below them still draws itself in', () => {
    const edges = graphEdges(
        [
            node({ id: 'parent', y: 40 }),
            node({ id: 'first', x: 100, y: 160, parent: 'parent' }),
            node({ id: 'second', x: 100, y: 300, parent: 'parent' }),
            node({ id: 'next', y: 500, after: 'parent' }),
        ],
        new Set(['parent->next']),
    );

    assert.deepEqual(
        edges.filter((edge) => edge.to !== 'next').map((edge) => edge.d),
        [
            'M 32 40 V 148 Q 32 160 44 160 H 100',
            'M 32 148 V 288 Q 32 300 44 300 H 100',
        ],
    );
    assert.ok(edges.some((edge) => edge.id === 'parent->next'));
});

void test('keeps the full elbow when the trunk below ends before the reply', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'next', y: 100, after: 'parent' }),
        node({ id: 'reply', x: 100, y: 160, parent: 'parent' }),
    ]);
    const branch = edges.find((edge) => edge.id === 'branch:reply');

    assert.ok(branch);
    assert.equal(branch.d, 'M 32 40 V 148 Q 32 160 44 160 H 100');
});

void test('tightens the corner when the reply sits closer than the corner radius', () => {
    const [edge] = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'reply', x: 40, y: 160, parent: 'parent' }),
    ]);

    assert.equal(edge.d, 'M 32 40 V 152 Q 32 160 40 160 H 40');
});

void test('dims a line when either of its nodes is dimmed', () => {
    const edges = graphEdges([
        node({ id: 'a', y: 40, dimmed: true }),
        node({ id: 'b', y: 200, after: 'a' }),
        node({ id: 'c', y: 400, after: 'b' }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.id, edge.dimmed]),
        [
            ['a->b', true],
            ['b->c', false],
        ],
    );
});

void test('tells a measure that moved nothing from one that moved a node', () => {
    const measured = [node({ id: 'a', y: 40 }), node({ id: 'b', y: 200 })];

    assert.equal(
        sameNodes(measured, [
            node({ id: 'a', y: 40 }),
            node({ id: 'b', y: 200 }),
        ]),
        true,
    );
    assert.equal(
        sameNodes(measured, [
            node({ id: 'a', y: 40 }),
            node({ id: 'b', y: 304 }),
        ]),
        false,
    );
    assert.equal(sameNodes(measured, measured.slice(0, 1)), false);
});

void test('names the nodes a line joins', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, parent: 'parent' }),
        node({ id: 'next', y: 400, after: 'parent' }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.from, edge.to]),
        [
            ['parent', 'reply'],
            ['parent', 'next'],
        ],
    );
});

void test("keeps a reply's branch the same line when its parent's end dot gives way to the commit, as the thread goes on", () => {
    const replies = [
        node({ id: 'first', x: 100, y: 160 }),
        node({ id: 'second', x: 100, y: 300 }),
    ];
    const ending = graphEdges([
        node({ id: 'commit', y: 40 }),
        node({ id: 'commit:end', y: 100, after: 'commit' }),
        ...replies.map((reply) => ({ ...reply, parent: 'commit:end' })),
    ]);
    const goingOn = graphEdges([
        node({ id: 'commit', y: 40 }),
        node({ id: 'next', y: 400, after: 'commit' }),
        ...replies.map((reply) => ({ ...reply, parent: 'commit' })),
    ]);
    const branchIds = (edges: typeof ending) =>
        edges
            .filter((edge) => edge.to !== 'commit:end' && edge.to !== 'next')
            .map((edge) => edge.id);

    assert.deepEqual(branchIds(goingOn), branchIds(ending));
});

void test('waits for the nodes of a line to finish scaling in before drawing it', () => {
    assert.equal(
        entranceDelay([
            { name: 'node-in', remaining: 650 },
            { name: 'node-in', remaining: 300 },
            { name: 'star-pop', remaining: 900 },
        ]),
        650,
    );
    assert.equal(entranceDelay([{ name: 'node-in', remaining: -20 }]), 0);
    assert.equal(
        entranceDelay([
            { name: 'node-in', remaining: 400 },
            { name: 'node-in', remaining: 1900, isWaiting: true, trails: true },
        ]),
        400,
    );
    assert.equal(entranceDelay([]), 0);
});

void test('waits for a node still waiting to scale in, like a chained commit waiting for the trunk to reach it', () => {
    assert.equal(
        entranceDelay([{ name: 'node-in', remaining: 1050, isWaiting: true }]),
        1050,
    );
});

void test('tells whether a line shows in the viewport, from where its canvas sits', () => {
    const [line] = graphEdges([
        node({ id: 'a', y: 1000 }),
        node({ id: 'b', y: 1200, after: 'a' }),
    ]);

    assert.equal(isOnScreen(line, -900, 800), true, 'inside the viewport');
    assert.equal(
        isOnScreen(line, -500, 800),
        true,
        'its top within the viewport',
    );
    assert.equal(isOnScreen(line, -1150, 800), true, 'crossing the top edge');
    assert.equal(isOnScreen(line, 0, 800), false, 'below the viewport');
    assert.equal(isOnScreen(line, -1300, 800), false, 'above the viewport');
    assert.equal(isOnScreen(line, -200, 800), true, 'touching the bottom edge');
});

void test('keeps a node the same while only its dimming changes, and tells it apart when it moves in the graph', () => {
    const spec = { id: 'a', lane: 'var(--lane-1)', after: 'b', ring: 2 };

    assert.equal(
        nodeIdentity({ ...spec, dimmed: true }),
        nodeIdentity({ ...spec, dimmed: false }),
    );
    assert.equal(nodeIdentity({ ...spec, dimmed: true }), nodeIdentity(spec));
    assert.notEqual(nodeIdentity({ ...spec, after: 'c' }), nodeIdentity(spec));
    assert.notEqual(nodeIdentity({ ...spec, parent: 'p' }), nodeIdentity(spec));
    assert.notEqual(nodeIdentity({ ...spec, ring: 0 }), nodeIdentity(spec));
    assert.notEqual(
        nodeIdentity({ ...spec, lane: 'var(--lane-2)' }),
        nodeIdentity(spec),
    );
    assert.notEqual(
        nodeIdentity({ ...spec, dashed: true }),
        nodeIdentity(spec),
    );
    assert.notEqual(
        nodeIdentity({ ...spec, isLeaving: true }),
        nodeIdentity(spec),
    );
});

void test('links nothing to a node on its way out, which only keeps its cut-out', () => {
    const edges = graphEdges([
        node({ id: 'commit', y: 40 }),
        node({ id: 'commit:end', y: 180, isLeaving: true }),
        node({ id: 'next', y: 300, after: 'commit' }),
    ]);

    assert.deepEqual(
        edges.map((edge) => edge.id),
        ['commit->next'],
    );
});

void test('tells apart the same commit shown in two places, like a quote listed under the commit it quotes', () => {
    assert.equal(scopedNodeId(null, 'reply'), 'reply');
    assert.equal(scopedNodeId('card', 'reply'), 'card/reply');
    assert.notEqual(scopedNodeId('card', 'reply'), scopedNodeId(null, 'reply'));
    assert.notEqual(
        scopedNodeId('card', 'reply'),
        scopedNodeId('other-card', 'reply'),
    );
});

void test('leaves out links to nodes that are not measured', () => {
    assert.deepEqual(
        graphEdges([
            node({ id: 'b', after: 'missing' }),
            node({ id: 'c', parent: 'missing' }),
        ]),
        [],
    );
});

void test('fades a line with the row it leaves with, and clips it to the row when both its nodes are in it', () => {
    const [inside, into, apart] = graphEdges([
        node({ id: 'a', y: 40 }),
        node({ id: 'b', y: 200, after: 'a' }),
        node({ id: 'c', y: 400, after: 'b' }),
        node({ id: 'd', y: 600, after: 'c' }),
    ]);
    const rows = [
        { ids: new Set(['a', 'b']), opacity: 0.4 },
        { ids: new Set(['d']), opacity: 0.7 },
    ];

    assert.deepEqual(leavingOf(inside, rows), { opacity: 0.4, clip: 0 });
    assert.deepEqual(leavingOf(into, rows), { opacity: 0.4 });
    assert.deepEqual(leavingOf(apart, rows), { opacity: 0.7 });
    assert.equal(leavingOf(inside, []), null);
});

void test('fades a line between two leaving rows like the fainter one, clipped to neither', () => {
    const [edge] = graphEdges([
        node({ id: 'a', y: 40 }),
        node({ id: 'b', y: 200, after: 'a' }),
    ]);

    assert.deepEqual(
        leavingOf(edge, [
            { ids: new Set(['a']), opacity: 0.4 },
            { ids: new Set(['b']), opacity: 0.2 },
        ]),
        { opacity: 0.2 },
    );
});

void test('keeps the stretch a line takes over from the one its node showed before, like a "more replies" dot giving way to the replies', () => {
    const [toDot] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'more', y: 120, after: 'reply' }),
    ]);
    const [toNext] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'next', y: 140, after: 'reply' }),
    ]);
    const [toCloser] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'next', y: 100, after: 'reply' }),
    ]);

    assert.equal(drawnStretch(toNext, [toDot]), 80);
    assert.equal(drawnStretch(toCloser, [toDot]), 60);
});

void test('keeps the straight stretch a branch shares with the branch it takes over', () => {
    const [toDot] = graphEdges([
        node({ id: 'root', y: 40 }),
        node({ id: 'more', x: 100, y: 120, parent: 'root' }),
    ]);
    const [toReply] = graphEdges([
        node({ id: 'root', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, parent: 'root' }),
    ]);

    assert.equal(drawnStretch(toReply, [toDot]), 68);
});

void test('keeps the stretch of the column a "more replies" dot ran down, once the reply below takes its place', () => {
    const replies = [
        node({ id: 'root', y: 40 }),
        node({ id: 'reply', x: 100, y: 160, parent: 'root' }),
    ];
    const toDot = graphEdges([
        ...replies,
        node({ id: 'more', y: 240, parent: 'root' }),
    ]).find((edge) => edge.to === 'more');
    const toNext = graphEdges([
        ...replies,
        node({ id: 'next', x: 100, y: 300, parent: 'root' }),
    ]).find((edge) => edge.to === 'next');

    assert.ok(toDot && toNext);
    assert.equal(drawnStretch(toNext, [toDot]), 92);
});

void test('bridges a line taking over a painted stretch to its node right away, as the node waits for it, like a reply chained under the last commit', () => {
    const [toEnd] = graphEdges([
        node({ id: 'commit', y: 40 }),
        node({ id: 'commit:end', y: 180, after: 'commit' }),
    ]);
    const [toReply] = graphEdges([
        node({ id: 'commit', y: 40 }),
        node({ id: 'reply', y: 220, after: 'commit' }),
    ]);

    assert.deepEqual(entranceDraw(toReply, [toEnd], 700, 260), {
        delay: 0,
        from: 140,
        duration: 260,
    });
    assert.deepEqual(entranceDraw(toReply, [toEnd], 700, 0), {
        delay: 0,
        from: 140,
        duration: bridgeDuration,
    });
});

void test('tells how long until a node waiting to scale in starts, like a chained commit waiting for the trunk to reach it', () => {
    assert.equal(
        nodeStartsIn([
            { name: 'node-in', remaining: 960, isWaiting: true, startsIn: 260 },
            {
                name: 'node-in',
                remaining: 1860,
                isWaiting: true,
                startsIn: 1160,
                trails: true,
            },
            {
                name: 'star-pop',
                remaining: 900,
                isWaiting: true,
                startsIn: 500,
            },
        ]),
        260,
    );
    assert.equal(nodeStartsIn([{ name: 'node-in', remaining: 400 }]), 0);
});

void test('draws in the rest of a line it takes over right away, and a new line in full once its nodes have scaled in', () => {
    const [toDot] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'more', y: 120, after: 'reply' }),
    ]);
    const [toNext] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'next', y: 140, after: 'reply' }),
    ]);

    assert.deepEqual(entranceDraw(toNext, [toDot], 0, 0), {
        delay: 0,
        from: 80,
        duration: null,
    });
    assert.deepEqual(entranceDraw(toNext, [], 700, 0), {
        delay: 700,
        from: 0,
        duration: null,
    });
});

void test('keeps nothing of a line from another node, from where its node stood before, or that turns away at once', () => {
    const [fromOther] = graphEdges([
        node({ id: 'other', y: 40 }),
        node({ id: 'more', y: 120, after: 'other' }),
    ]);
    const [fromAbove] = graphEdges([
        node({ id: 'reply', y: 20 }),
        node({ id: 'more', y: 120, after: 'reply' }),
    ]);
    const [aside] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'more', x: 60, y: 120, after: 'reply' }),
    ]);
    const [toNext] = graphEdges([
        node({ id: 'reply', y: 40 }),
        node({ id: 'next', y: 140, after: 'reply' }),
    ]);

    assert.equal(drawnStretch(toNext, [fromOther]), 0);
    assert.equal(drawnStretch(toNext, [fromAbove]), 0);
    assert.equal(drawnStretch(toNext, [aside]), 0);
    assert.equal(drawnStretch(toNext, []), 0);
});
