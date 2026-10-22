export type Point = { x: number; y: number };

/**
 * A node of the commit graph, measured where it is drawn: its centre and the radius of its outer ring.
 *
 * A node follows `after` on the same trunk (the thread rail, or the previous reply at the same depth), and branches
 * off `parent` when it is the first reply to it.
 */
export type GraphNode = Point & {
    id: string;
    radius: number;
    lane: string;
    after?: string;
    parent?: string;
    dashed?: boolean;
    dimmed?: boolean;
};

export type GraphEdge = {
    id: string;
    from: string;
    to: string;
    d: string;
    start: Point;
    end: Point;
    /** How far the line runs straight down from its start before it turns, if it does. */
    drop: number;
    /** The lane of the node the line leaves, which it keeps to the end. */
    lane: string;
    dashed: boolean;
    dimmed: boolean;
};

const cornerRadius = 12;

/**
 * Every line between the nodes, each one a single path so its dashes run on without a seam. Lines go
 * from centre to centre: the canvas cuts the nodes out, so a line always meets a ring exactly and never crosses one.
 */
export function graphEdges(nodes: GraphNode[]): GraphEdge[] {
    const byId = new Map(nodes.map((node) => [node.id, node]));
    const edges: GraphEdge[] = [];

    for (const node of nodes) {
        const previous =
            node.after === undefined ? undefined : byId.get(node.after);

        if (previous) {
            edges.push(trunk(previous, node));
        }

        const parent =
            node.parent === undefined ? undefined : byId.get(node.parent);

        if (parent) {
            const continues = nodes.some(
                (other) => other.after === parent.id && other.y > node.y,
            );

            edges.push(branch(parent, node, continues));
        }
    }

    return edges;
}

/**
 * How long a new line waits before drawing itself in: a node that scales in overshoots its ring, so the line would
 * run over it. A node still waiting to scale in, like an end dot that only pops once its line is drawn, doesn't hold
 * the line back.
 */
export function entranceDelay(
    animations: { name: string; remaining: number; isWaiting?: boolean }[],
): number {
    return Math.max(
        0,
        ...animations
            .filter(
                (animation) =>
                    animation.name === 'node-in' && !animation.isWaiting,
            )
            .map((animation) => animation.remaining),
    );
}

/**
 * Whether a line shows in the viewport, given how far down the viewport its canvas starts. A line that shows up off
 * screen, like those of cards loaded ahead of the scroll, has no one to draw itself in for.
 */
export function isOnScreen(
    edge: GraphEdge,
    canvasTop: number,
    viewportHeight: number,
): boolean {
    const top = canvasTop + Math.min(edge.start.y, edge.end.y);
    const bottom = canvasTop + Math.max(edge.start.y, edge.end.y);

    return bottom >= 0 && top <= viewportHeight;
}

/**
 * How much of a new line, from its start, is already painted by a line that just left the same node from the same
 * place, like the line to a "more replies" dot once the replies it stood for take its place: that stretch stays, and
 * only the rest draws itself in.
 */
export function drawnStretch(edge: GraphEdge, gone: GraphEdge[]): number {
    return Math.max(
        0,
        ...gone
            .filter(
                (previous) =>
                    previous.from === edge.from &&
                    previous.start.x === edge.start.x &&
                    previous.start.y === edge.start.y,
            )
            .map((previous) => Math.min(previous.drop, edge.drop)),
    );
}

/**
 * What places a node in the graph, so it has to be registered and measured again when it changes. Dimming only
 * changes how its lines look.
 */
export function nodeIdentity(spec: {
    id: string;
    lane: string;
    after?: string;
    parent?: string;
    dashed?: boolean;
    ring?: number;
    dimmed?: boolean;
}): string {
    // Kept as given, since a missing `dashed` lets a line take its other node's.
    return JSON.stringify({
        id: spec.id,
        lane: spec.lane,
        after: spec.after,
        parent: spec.parent,
        dashed: spec.dashed,
        ring: spec.ring,
    });
}

/**
 * A node's id within the card that shows it. A commit can show twice in one canvas, like a quote listed under the
 * commit it quotes and as a card of its own, and each place needs a node of its own.
 */
export function scopedNodeId(scope: string | null, id: string): string {
    return scope === null ? id : `${scope}/${id}`;
}

/** A row on its way out, like a deleted card collapsing: the nodes it holds and how faded it is. */
export type LeavingRow = { ids: Set<string>; opacity: number };

/**
 * How a line shows while a row it touches leaves: as faded as the faintest of them, and clipped to the row (by its
 * index) when the line lies within it, as the rail inside the row was.
 */
export function leavingOf(
    edge: GraphEdge,
    rows: LeavingRow[],
): { opacity: number; clip?: number } | null {
    const touched = rows.filter(
        (row) => row.ids.has(edge.from) || row.ids.has(edge.to),
    );

    if (touched.length === 0) {
        return null;
    }

    const opacity = Math.min(...touched.map((row) => row.opacity));
    const clip = rows.findIndex(
        (row) => row.ids.has(edge.from) && row.ids.has(edge.to),
    );

    return clip === -1 ? { opacity } : { opacity, clip };
}

export function sameNodes(a: GraphNode[], b: GraphNode[]): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

function trunk(from: GraphNode, to: GraphNode): GraphEdge {
    return {
        id: `${from.id}->${to.id}`,
        from: from.id,
        to: to.id,
        d:
            from.x === to.x
                ? `M ${from.x} ${from.y} V ${to.y}`
                : `M ${from.x} ${from.y} L ${to.x} ${to.y}`,
        start: { x: from.x, y: from.y },
        end: { x: to.x, y: to.y },
        drop: from.x === to.x ? Math.max(0, to.y - from.y) : 0,
        lane: from.lane,
        dashed: to.dashed ?? from.dashed ?? false,
        dimmed: isDimmed(from, to),
    };
}

/**
 * The first reply hangs off its parent with a rounded corner. When the parent's trunk goes on below the reply, the
 * branch only starts at the corner, so it doesn't draw a second line over the trunk.
 */
function branch(from: GraphNode, to: GraphNode, continues: boolean): GraphEdge {
    const radius = Math.max(
        0,
        Math.min(cornerRadius, to.x - from.x, to.y - from.y),
    );
    const corner = to.y - radius;
    const start = continues
        ? { x: from.x, y: corner }
        : { x: from.x, y: from.y };
    const head = continues
        ? `M ${from.x} ${corner}`
        : `M ${from.x} ${from.y} V ${corner}`;

    return {
        id: `${from.id}->${to.id}`,
        from: from.id,
        to: to.id,
        d: `${head} Q ${from.x} ${to.y} ${from.x + radius} ${to.y} H ${to.x}`,
        start,
        end: { x: to.x, y: to.y },
        drop: Math.max(0, corner - start.y),
        lane: from.lane,
        dashed: true,
        dimmed: isDimmed(from, to),
    };
}

function isDimmed(from: GraphNode, to: GraphNode): boolean {
    return Boolean(from.dimmed || to.dimmed);
}
