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
import { entranceDelay, graphEdges, isOnScreen, sameNodes } from '@/lib/graph';
import type { GraphEdge, GraphNode } from '@/lib/graph';
import { cn } from '@/lib/utils';

export type GraphNodeSpec = Pick<
    GraphNode,
    'id' | 'lane' | 'after' | 'parent' | 'dashed'
> & {
    /** How far the node's ring reaches beyond its box, like a box-shadow ring. */
    ring?: number;
};

type Registry = {
    register: (element: HTMLElement, spec: GraphNodeSpec) => void;
    unregister: (id: string) => void;
};

const GraphContext = createContext<Registry | null>(null);

/**
 * True once the canvas has measured its nodes. Until then, as on the server, the rail pieces stay, so the first paint
 * matches the server's and a page without JavaScript keeps its rail.
 */
const GraphDrawingContext = createContext(false);

const strokeWidth = 2;

const dash = '4 4';

/** How much of a line keeps the first lane before fading into the next. */
const fadeHold = 0.5;

/**
 * Whether the lines around a node are drawn by a graph canvas, in which case the node's own rail pieces stay out.
 */
export function useGraphOverlay(): boolean {
    return useContext(GraphDrawingContext);
}

/**
 * Registers the element it is given as a node of the surrounding graph canvas, which measures it and draws its lines.
 */
export function useGraphNode(
    spec: GraphNodeSpec | null,
): (element: HTMLElement | null) => void {
    const registry = useContext(GraphContext);
    const key = spec === null ? null : JSON.stringify(spec);

    return useCallback(
        (element: HTMLElement | null) => {
            if (registry === null || key === null) {
                return;
            }

            const registered = JSON.parse(key) as GraphNodeSpec;

            if (element === null) {
                registry.unregister(registered.id);

                return;
            }

            registry.register(element, registered);
        },
        [registry, key],
    );
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
    const observer = useRef<ResizeObserver | null>(null);
    const frame = useRef(0);
    const [nodes, setNodes] = useState<GraphNode[]>([]);
    const [size, setSize] = useState<Size>({ width: 0, height: 0 });
    const [isDrawing, setIsDrawing] = useState(false);

    const read = useCallback(() => {
        const container = containerRef.current;

        if (!container) {
            return;
        }

        const box = container.getBoundingClientRect();
        const measured = [...entries.current.values()]
            .filter(({ element }) => element.isConnected)
            .map(({ element, spec }) => {
                const rect = element.getBoundingClientRect();

                return {
                    id: spec.id,
                    lane: spec.lane,
                    after: spec.after,
                    parent: spec.parent,
                    dashed: spec.dashed,
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
        setNodes((current) =>
            sameNodes(current, measured) ? current : measured,
        );
    }, []);

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
                }

                measure();
            },
        }),
        [measure],
    );

    useLayoutEffect(() => {
        // An observer reports each element once when it starts watching it, as a page mounts: the registration has
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

        for (const { element } of entries.current.values()) {
            resize.observe(element);
        }

        // Nodes also move without anything resizing, like cards trading places when the feed reorders.
        const mutations = new MutationObserver((records) => {
            if (
                records.some(
                    (record) => !canvasRef.current?.contains(record.target),
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
        void document.fonts?.ready.then(measure);
        measure();

        return () => {
            resize.disconnect();
            mutations.disconnect();
            container?.removeEventListener('animationend', settle);
            observer.current = null;
            window.removeEventListener('resize', measure);
            cancelAnimationFrame(frame.current);
        };
    }, [measure, measureNow]);

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
                                    />
                                ))}
                            </mask>
                            {edges.map((edge, index) => (
                                <EdgeDefs
                                    key={edge.id}
                                    id={`${id}-${index}`}
                                    edge={edge}
                                    drawDelay={entering.get(edge.id)}
                                    onDrawn={onDrawn}
                                />
                            ))}
                        </defs>
                        <g mask={`url(#${id}-nodes)`}>
                            {edges.map((edge, index) => (
                                <path
                                    key={edge.id}
                                    d={edge.d}
                                    style={{
                                        stroke:
                                            edge.fromLane === edge.toLane
                                                ? edge.fromLane
                                                : `url(#${id}-${index}-fade)`,
                                    }}
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
                            ))}
                        </g>
                    </svg>
                </div>
            </GraphDrawingContext.Provider>
        </GraphContext.Provider>
    );
}

function EdgeDefs({
    id,
    edge,
    drawDelay,
    onDrawn,
}: {
    id: string;
    edge: GraphEdge;
    /** Set while the line draws itself in, after its nodes have scaled in. */
    drawDelay?: number;
    onDrawn: (edgeId: string) => void;
}) {
    return (
        <>
            {edge.fromLane !== edge.toLane && (
                <linearGradient
                    id={`${id}-fade`}
                    gradientUnits="userSpaceOnUse"
                    x1={edge.start.x}
                    y1={edge.start.y}
                    x2={edge.end.x}
                    y2={edge.end.y}
                >
                    <stop offset="0%" style={{ stopColor: edge.fromLane }} />
                    <stop
                        offset={`${fadeHold * 100}%`}
                        style={{ stopColor: edge.fromLane }}
                    />
                    <stop offset="100%" style={{ stopColor: edge.toLane }} />
                </linearGradient>
            )}
            {drawDelay !== undefined && (
                <mask id={`${id}-draw`} maskUnits="userSpaceOnUse">
                    <path
                        d={edge.d}
                        stroke="white"
                        strokeWidth={strokeWidth * 4}
                        pathLength={1}
                        strokeDasharray="1 1"
                        style={{ animationDelay: `${drawDelay}ms` }}
                        className="motion-safe:animate-graph-draw"
                        onAnimationEnd={() => onDrawn(edge.id)}
                    />
                </mask>
            )}
        </>
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
): { entering: Map<string, number>; onDrawn: (edgeId: string) => void } {
    const known = useRef<Set<string> | null>(null);
    const [entering, setEntering] = useState<Map<string, number>>(new Map());

    // Before the browser paints, so a new line never shows in full for a frame before its mask hides it.
    useLayoutEffect(() => {
        const ids = new Set(edges.map((edge) => edge.id));

        if (known.current === null) {
            if (edges.length > 0) {
                known.current = ids;
            }

            return;
        }

        const fresh = [...ids].filter((edgeId) => !known.current?.has(edgeId));
        known.current = ids;
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
                ...drawing.map((edge): [string, number] => [
                    edge.id,
                    delayOf(edge),
                ]),
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
