import assert from 'node:assert/strict';
import { test } from 'node:test';
import {
    drawnStretch,
    entranceDelay,
    graphEdges,
    isOnScreen,
    leavingOf,
    nodeIdentity,
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
            after: 'reply',
            lane: 'var(--lane-3)',
        }),
    ]);

    assert.deepEqual(
        edges.map((edge) => [edge.id, edge.lane]),
        [
            ['root->reply', 'var(--lane-1)'],
            ['reply->sibling', 'var(--lane-2)'],
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
    const branch = edges.find((edge) => edge.id === 'parent->reply');

    assert.ok(branch);
    assert.equal(branch.d, 'M 32 148 Q 32 160 44 160 H 100');
});

void test('keeps the full elbow when the trunk below ends before the reply', () => {
    const edges = graphEdges([
        node({ id: 'parent', y: 40 }),
        node({ id: 'next', y: 100, after: 'parent' }),
        node({ id: 'reply', x: 100, y: 160, parent: 'parent' }),
    ]);
    const branch = edges.find((edge) => edge.id === 'parent->reply');

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
            { name: 'node-in', remaining: 1900, isWaiting: true },
        ]),
        400,
    );
    assert.equal(entranceDelay([]), 0);
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
