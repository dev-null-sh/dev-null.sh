import { router } from '@inertiajs/react';
import type { ComponentProps, ReactNode } from 'react';
import {
    createContext,
    useCallback,
    useContext,
    useId,
    useLayoutEffect,
    useMemo,
    useRef,
    useState,
} from 'react';
import { flushSync } from 'react-dom';
import {
    drawnStretch,
    entranceDelay,
    graphEdges,
    isOnScreen,
    leavingOf,
    nodeIdentity,
    sameNodes,
    scopedNodeId,
} from '@/lib/graph';
import type { GraphEdge, GraphNode, LeavingRow } from '@/lib/graph';
import { cn } from '@/lib/utils';

export type GraphNodeSpec = Pick<
    GraphNode,
    'id' | 'lane' | 'after' | 'parent' | 'dashed' | 'dimmed'
> & {
    /** How far the node's ring reaches beyond its box, like a box-shadow ring. */
    ring?: number;
};

type Registry = {
    register: (element: HTMLElement, spec: GraphNodeSpec) => void;
    unregister: (id: string) => void;
    /** Dims a node's lines or brings them back, without measuring anything again. */
    dim: (id: string, isDimmed: boolean) => void;
};

const GraphContext = createContext<Registry | null>(null);

/**
 * True once the canvas has measured its nodes. Until then, as on the server, the rail pieces stay, so the first paint
 * matches the server's and a page without JavaScript keeps its rail.
 */
const GraphDrawingContext = createContext(false);

const strokeWidth = 2;

const dash = '4 4';

/**
 * Whether the lines around a node are drawn by a graph canvas, in which case the node's own rail pieces stay out.
 */
export function useGraphOverlay(): boolean {
    return useContext(GraphDrawingContext);
}

/**
 * Registers the element it is given as a node of the surrounding graph canvas, which measures it and draws its lines.
 *
 * Only a change to where the node sits in the graph registers it again: hovering dims lanes on every card, which would
 * otherwise have each one leave the canvas and be measured again.
 */
export function useGraphNode(
    spec: GraphNodeSpec | null,
): (element: HTMLElement | null) => void {
    const registry = useContext(GraphContext);
    const identity = spec === null ? null : nodeIdentity(spec);
    const id = spec?.id;
    const isDimmed = spec?.dimmed ?? false;

    useLayoutEffect(() => {
        if (registry === null || id === undefined) {
            return;
        }

        registry.dim(id, isDimmed);
    }, [registry, id, isDimmed]);

    return useCallback(
        (element: HTMLElement | null) => {
            if (registry === null || identity === null) {
                return;
            }

            const registered = JSON.parse(identity) as GraphNodeSpec;

            if (element === null) {
                registry.unregister(registered.id);

                return;
            }

            registry.register(element, registered);
        },
        [registry, identity],
    );
}

const GraphDimmedContext = createContext(false);

/**
 * Dims the lines of the nodes inside it, like the replies of a commit whose lane is dimmed.
 */
export function GraphDimmed({
    isDimmed,
    children,
}: {
    isDimmed: boolean;
    children: ReactNode;
}) {
    return (
        <GraphDimmedContext.Provider value={isDimmed}>
            {children}
        </GraphDimmedContext.Provider>
    );
}

export function useGraphDimmed(): boolean {
    return useContext(GraphDimmedContext);
}

const GraphScopeContext = createContext<string | null>(null);

/**
 * Gives the nodes inside it ids of their own, like the replies a card lists: the same commit can also show as a card,
 * or under another card, in the same canvas.
 */
export function GraphScope({
    scope,
    children,
}: {
    scope: string;
    children: ReactNode;
}) {
    return (
        <GraphScopeContext.Provider value={scope}>
            {children}
        </GraphScopeContext.Provider>
    );
}

/** The node id of a commit within the surrounding scope. */
export function useGraphNodeId(): (id: string) => string {
    const scope = useContext(GraphScopeContext);

    return useCallback((id: string) => scopedNodeId(scope, id), [scope]);
}

/**
 * Draws its children, which are rail lines, only when no graph canvas draws the lines instead.
 */
export function WithoutGraph({ children }: { children: ReactNode }) {
    return useGraphOverlay() ? null : children;
}

/**
 * A span that is a node of the surrounding graph canvas, when given a spec.
 *
 * A fresh node scales in only when it shows up after its canvas first drew, like a commit just posted to the feed:
 * one already there when the page opens stays still, as its lines do.
 */
export function GraphAnchor({
    spec,
    isFresh = false,
    className,
    ...props
}: ComponentProps<'span'> & {
    spec: GraphNodeSpec | null;
    isFresh?: boolean;
}) {
    const isInCanvas = useContext(GraphContext) !== null;
    const isDrawing = useGraphOverlay();
    const [showsUpLive] = useState(!isInCanvas || isDrawing);

    return (
        <span
            ref={useGraphNode(spec)}
            className={cn(className, {
                'motion-safe:animate-node-in': isFresh && showsUpLive,
            })}
            {...props}
        />
    );
}

type Size = { width: number; height: number };

/** A leaving row, with the part of it still showing, as the canvas measures it. */
type Leaving = LeavingRow & Size & { x: number; y: number };

const noLeaving: Leaving[] = [];

/**
 * A row marked `data-graph-leaving`, like a deleted card fading and collapsing: the part of it its clipping still
 * shows, and how faded it is up to the canvas.
 */
function leavingRow(
    element: Element,
    box: DOMRect,
    container: Element,
): Leaving {
    const rect = element.getBoundingClientRect();
    let opacity = 1;

    for (
        let current: Element | null = element;
        current !== null && current !== container;
        current = current.parentElement
    ) {
        opacity *= Number(getComputedStyle(current).opacity);
    }

    return {
        x: rect.left - box.left,
        y: rect.top - box.top,
        width: rect.width,
        height: rect.height,
        opacity,
        ids: new Set(),
    };
}

function snap(value: number): number {
    const ratio = window.devicePixelRatio || 1;

    return Math.round(value * ratio) / ratio;
}

/**
 * The CSS animations running on a node and on the elements around it, up to the canvas, with the time they have left.
 */
function runningAnimations(
    element: Element,
    container: Element | null,
): { name: string; remaining: number; isWaiting: boolean }[] {
    const running: { name: string; remaining: number; isWaiting: boolean }[] =
        [];

    for (
        let current: Element | null = element;
        current !== null && current !== container;
        current = current.parentElement
    ) {
        for (const animation of current.getAnimations()) {
            if (animation instanceof CSSAnimation) {
                const currentTime = Number(animation.currentTime);

                running.push({
                    name: animation.animationName,
                    remaining:
                        Number(animation.effect?.getComputedTiming().endTime) -
                        currentTime,
                    isWaiting:
                        currentTime <
                        Number(animation.effect?.getTiming().delay ?? 0),
                });
            }
        }
    }

    return running;
}

export function GraphCanvas({ children }: { children: ReactNode }) {
    const id = useId().replaceAll(':', '');
    const containerRef = useRef<HTMLDivElement>(null);
    const canvasRef = useRef<SVGSVGElement>(null);
    const entries = useRef(
        new Map<string, { element: HTMLElement; spec: GraphNodeSpec }>(),
    );
    // Nodes that left since the last mutations, whose removal those mutations hold.
    const departed = useRef(new Set<HTMLElement>());
    const dims = useRef(new Map<string, boolean>());
    const observer = useRef<ResizeObserver | null>(null);
    const frame = useRef(0);
    const [nodes, setNodes] = useState<GraphNode[]>([]);
    const [size, setSize] = useState<Size>({ width: 0, height: 0 });
    const [leaving, setLeaving] = useState(noLeaving);
    const [isDrawing, setIsDrawing] = useState(false);

    const clip = useCallback(() => {
        const box = containerRef.current?.getBoundingClientRect();
        const canvas = canvasRef.current;

        if (box && canvas) {
            // Large offscreen SVGs distort in Gecko's view-transition captures. Keep their painted area in the viewport.
            canvas.style.clipPath = `inset(${Math.max(0, -box.top)}px 0 ${Math.max(0, box.bottom - window.innerHeight)}px 0)`;
        }
    }, []);

    const read = useCallback(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const box = container.getBoundingClientRect();
        clip();
        const rows = [
            ...container.querySelectorAll('[data-graph-leaving]'),
        ].map((element) => ({
            element,
            row: leavingRow(element, box, container),
        }));
        const measured = [...entries.current.values()]
            .filter(({ element }) => element.isConnected)
            .map(({ element, spec }) => {
                const rect = element.getBoundingClientRect();
                rows.find((row) => row.element.contains(element))?.row.ids.add(
                    spec.id,
                );

                return {
                    id: spec.id,
                    lane: spec.lane,
                    after: spec.after,
                    parent: spec.parent,
                    dashed: spec.dashed,
                    dimmed: dims.current.get(spec.id) ?? false,
                    x: snap(rect.left - box.left + rect.width / 2),
                    y: snap(rect.top - box.top + rect.height / 2),
                    // The layout width, as a node scaling in would otherwise keep the radius it had mid-animation.
                    radius: element.offsetWidth / 2 + (spec.ring ?? 0),
                };
            });

        setSize((current) =>
            current.width === box.width && current.height === box.height
                ? current
                : { width: box.width, height: box.height },
        );
        setIsDrawing(true);
        setLeaving((current) =>
            rows.length === 0 && current.length === 0
                ? current
                : rows.map(({ row }) => row),
        );
        setNodes((current) =>
            sameNodes(current, measured) ? current : measured,
        );
    }, [clip]);

    const measure = useCallback(() => {
        cancelAnimationFrame(frame.current);
        frame.current = requestAnimationFrame(read);
    }, [read]);

    // Observers report before the browser paints, so the lines follow their nodes in the same frame.
    const measureNow = useCallback(() => {
        cancelAnimationFrame(frame.current);
        flushSync(read);
    }, [read]);

    const registry = useMemo<Registry>(
        () => ({
            register: (element, spec) => {
                entries.current.set(spec.id, { element, spec });
                observer.current?.observe(element);
                measure();
            },
            unregister: (nodeId) => {
                const entry = entries.current.get(nodeId);

                if (entry) {
                    observer.current?.unobserve(entry.element);
                    entries.current.delete(nodeId);
                    departed.current.add(entry.element);
                }

                measure();
            },
            dim: (nodeId, isDimmed) => {
                if ((dims.current.get(nodeId) ?? false) === isDimmed) {
                    return;
                }

                dims.current.set(nodeId, isDimmed);
                setNodes((current) =>
                    current.some((node) => node.id === nodeId)
                        ? current.map((node) =>
                              node.id === nodeId
                                  ? { ...node, dimmed: isDimmed }
                                  : node,
                          )
                        : current,
                );
            },
        }),
        [measure],
    );

    useLayoutEffect(() => {
        // An observer reports each element once when it starts watching it, as a page mounts: the layout effect has
        // measured already, and rendering then would run the new page's effects inside the observer's callback. Only
        // real resizes measure on the spot.
        const sizes = new WeakMap<Element, string>();
        const resize = new ResizeObserver((entries) => {
            let hasResized = false;

            for (const entry of entries) {
                const size = `${entry.contentRect.width}x${entry.contentRect.height}`;
                hasResized ||=
                    sizes.has(entry.target) && sizes.get(entry.target) !== size;
                sizes.set(entry.target, size);
            }

            if (hasResized) {
                measureNow();
            } else {
                measure();
            }
        });
        observer.current = resize;

        if (containerRef.current) {
            resize.observe(containerRef.current);
        }

        // The composer can move the canvas without resizing it.
        resize.observe(document.body);

        for (const { element } of entries.current.values()) {
            resize.observe(element);
        }

        // Nodes also move without anything resizing, like cards trading places when the feed reorders. Other changes
        // inside a card, like an image done loading, move no node unless they resize the container, which the resize
        // observer reports.
        const mutations = new MutationObserver((records) => {
            const elements = [
                ...[...entries.current.values()].map(({ element }) => element),
                ...departed.current,
            ];
            departed.current.clear();

            if (
                records.some((record) =>
                    [...record.addedNodes, ...record.removedNodes].some(
                        (node) =>
                            elements.some((element) => node.contains(element)),
                    ),
                )
            ) {
                measureNow();
            }
        });

        // A node scaling in is measured mid-animation, where an offset inside it is scaled down too.
        const settle = (event: AnimationEvent) => {
            if (!canvasRef.current?.contains(event.target as Node)) {
                measureNow();
            }
        };

        if (containerRef.current) {
            mutations.observe(containerRef.current, {
                childList: true,
                subtree: true,
            });
            containerRef.current.addEventListener('animationend', settle);
        }

        const container = containerRef.current;

        window.addEventListener('resize', measure);
        window.addEventListener('scroll', clip, {
            capture: true,
            passive: true,
        });
        // Inertia resets or restores scroll after React's layout effects; success also covers replace visits.
        const stopSuccess = router.on('success', clip);
        const stopNavigate = router.on('navigate', clip);
        void document.fonts?.ready.then(measure);
        // A view transition captures the new page before the next animation frame, so its graph must already be drawn.
        read();

        return () => {
            resize.disconnect();
            mutations.disconnect();
            container?.removeEventListener('animationend', settle);
            observer.current = null;
            window.removeEventListener('resize', measure);
            window.removeEventListener('scroll', clip, true);
            stopSuccess();
            stopNavigate();
            cancelAnimationFrame(frame.current);
        };
    }, [clip, measure, measureNow, read]);

    const edges = useMemo(() => graphEdges(nodes), [nodes]);
    const delayOf = useCallback(
        (edge: GraphEdge) =>
            entranceDelay(
                [edge.from, edge.to].flatMap((nodeId) => {
                    const entry = entries.current.get(nodeId);

                    return entry
                        ? runningAnimations(entry.element, containerRef.current)
                        : [];
                }),
            ),
        [],
    );
    const canvasTop = useCallback(
        () => containerRef.current?.getBoundingClientRect().top ?? 0,
        [],
    );
    const { entering, onDrawn } = useEnteringEdges(edges, delayOf, canvasTop);
    const clipOf = (row: number) =>
        row === -1 ? undefined : `url(#${id}-leaving-${row})`;

    return (
        <GraphContext.Provider value={registry}>
            <GraphDrawingContext.Provider value={isDrawing}>
                <div ref={containerRef} className="relative">
                    {children}
                    <svg
                        ref={canvasRef}
                        aria-hidden
                        className="pointer-events-none absolute inset-0 z-[5] overflow-visible"
                        width={size.width}
                        height={size.height}
                        fill="none"
                        data-test="graph-canvas"
                    >
                        <defs>
                            <mask
                                id={`${id}-nodes`}
                                maskUnits="userSpaceOnUse"
                                x={0}
                                y={0}
                                width={size.width}
                                height={size.height}
                            >
                                <rect
                                    width={size.width}
                                    height={size.height}
                                    fill="white"
                                />
                                {nodes.map((node) => (
                                    <circle
                                        key={node.id}
                                        cx={node.x}
                                        cy={node.y}
                                        r={node.radius}
                                        fill="black"
                                        clipPath={clipOf(
                                            leaving.findIndex((row) =>
                                                row.ids.has(node.id),
                                            ),
                                        )}
                                    />
                                ))}
                            </mask>
                            {leaving.map((row, index) => (
                                <clipPath
                                    key={index}
                                    id={`${id}-leaving-${index}`}
                                >
                                    <rect
                                        x={row.x}
                                        y={row.y}
                                        width={row.width}
                                        height={row.height}
                                    />
                                </clipPath>
                            ))}
                            {edges.map((edge, index) => (
                                <EdgeDefs
                                    key={edge.id}
                                    id={`${id}-${index}`}
                                    edge={edge}
                                    draw={entering.get(edge.id)}
                                    onDrawn={onDrawn}
                                />
                            ))}
                        </defs>
                        <g mask={`url(#${id}-nodes)`}>
                            {edges.map((edge, index) => {
                                const left = leavingOf(edge, leaving);

                                return (
                                    <path
                                        key={edge.id}
                                        d={edge.d}
                                        style={{
                                            stroke: edge.lane,
                                            // Measured as the row collapses, which fades along: the line follows as is.
                                            transition: left
                                                ? 'none'
                                                : undefined,
                                        }}
                                        opacity={
                                            (edge.dimmed ? 0.6 : 1) *
                                            (left?.opacity ?? 1)
                                        }
                                        clipPath={clipOf(left?.clip ?? -1)}
                                        className="transition-opacity duration-200"
                                        strokeWidth={strokeWidth}
                                        strokeDasharray={
                                            edge.dashed ? dash : undefined
                                        }
                                        mask={
                                            entering.has(edge.id)
                                                ? `url(#${id}-${index}-draw)`
                                                : undefined
                                        }
                                        data-test={
                                            edge.dashed
                                                ? 'graph-edge-dashed'
                                                : 'graph-edge'
                                        }
                                    />
                                );
                            })}
                        </g>
                    </svg>
                </div>
            </GraphDrawingContext.Provider>
        </GraphContext.Provider>
    );
}

/** How a line draws itself in: after how long, and from how far along, in px, as the stretch before is already painted. */
type Draw = { delay: number; from: number };

function EdgeDefs({
    id,
    edge,
    draw,
    onDrawn,
}: {
    id: string;
    edge: GraphEdge;
    /** Set while the line draws itself in, after its nodes have scaled in. */
    draw?: Draw;
    onDrawn: (edgeId: string) => void;
}) {
    const from = draw?.from ?? 0;
    const startAt = useCallback(
        (path: SVGPathElement | null) => {
            const length = path?.getTotalLength() ?? 0;

            path?.style.setProperty(
                '--graph-draw-from',
                String(length > 0 ? Math.max(0, 1 - from / length) : 1),
            );
        },
        [from],
    );

    if (draw === undefined) {
        return null;
    }

    return (
        <mask id={`${id}-draw`} maskUnits="userSpaceOnUse">
            <path
                ref={startAt}
                d={edge.d}
                stroke="white"
                strokeWidth={strokeWidth * 4}
                pathLength={1}
                strokeDasharray="1 1"
                style={{ animationDelay: `${draw.delay}ms` }}
                className="motion-safe:animate-graph-draw"
                onAnimationEnd={() => onDrawn(edge.id)}
            />
        </mask>
    );
}

/**
 * Edges that show up on screen after the first measure draw themselves in, like a reply that was just loaded. Those
 * already there when the page renders stay still, and so do those showing up off screen.
 */
function useEnteringEdges(
    edges: GraphEdge[],
    delayOf: (edge: GraphEdge) => number,
    canvasTop: () => number,
): { entering: Map<string, Draw>; onDrawn: (edgeId: string) => void } {
    const known = useRef<GraphEdge[] | null>(null);
    const [entering, setEntering] = useState<Map<string, Draw>>(new Map());

    // Before the browser paints, so a new line never shows in full for a frame before its mask hides it.
    useLayoutEffect(() => {
        const ids = new Set(edges.map((edge) => edge.id));

        if (known.current === null) {
            if (edges.length > 0) {
                known.current = edges;
            }

            return;
        }

        const before = new Set(known.current.map((edge) => edge.id));
        const fresh = [...ids].filter((edgeId) => !before.has(edgeId));
        const gone = known.current.filter((edge) => !ids.has(edge.id));
        known.current = edges;
        const top = canvasTop();
        // Without motion, no draw would ever end to take its mask off.
        const isStill = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const drawing = isStill
            ? []
            : edges.filter(
                  (edge) =>
                      fresh.includes(edge.id) &&
                      isOnScreen(edge, top, window.innerHeight),
              );

        // Lines already drawing keep going; those gone take their masks with them.
        setEntering((current) => {
            const kept = [...current].filter(([edgeId]) => ids.has(edgeId));

            if (drawing.length === 0 && kept.length === current.size) {
                return current;
            }

            return new Map([
                ...kept,
                ...drawing.map((edge): [string, Draw] => {
                    const delay = delayOf(edge);

                    // A line waiting for a node to scale in, like a reply just posted, draws in full once it has, so
                    // no stretch of it runs over the node as it overshoots.
                    return [
                        edge.id,
                        {
                            delay,
                            from: delay > 0 ? 0 : drawnStretch(edge, gone),
                        },
                    ];
                }),
            ]);
        });
    }, [edges, delayOf, canvasTop]);

    // A line done drawing drops its mask, which would otherwise cost a mask on every paint.
    const onDrawn = useCallback((edgeId: string) => {
        setEntering((current) => {
            if (!current.has(edgeId)) {
                return current;
            }

            const next = new Map(current);
            next.delete(edgeId);

            return next;
        });
    }, []);

    return { entering, onDrawn };
}
