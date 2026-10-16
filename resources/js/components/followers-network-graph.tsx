import { router } from '@inertiajs/react';
import { ZoomIn, ZoomOut, Maximize } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { cn, laneColor, toUrl } from '@/lib/utils';
import { show } from '@/routes/user';
import type { User } from '@/types';

export type FollowerGraphNode = Pick<
    User,
    'id' | 'username' | 'name' | 'avatar_url' | 'lane'
> & { followers_count: number; followed_back: boolean };

export type FollowerGraphEdge = {
    source: string;
    target: string;
};

export type FollowerGraphFilter =
    | 'everyone'
    | 'followed-back'
    | 'not-followed-back';

type PlacedNode = FollowerGraphNode & {
    x: number;
    y: number;
    radius: number;
};

const GOLDEN_ANGLE = Math.PI * (3 - Math.sqrt(5));
const WORLD = 520;

function initialsOf(name: string): string {
    const parts = name.trim().split(/\s+/u).filter(Boolean);

    if (parts.length === 0) {
        return '?';
    }

    if (parts.length === 1) {
        return (Array.from(parts[0])[0] ?? '?').toUpperCase();
    }

    return (
        (Array.from(parts[0])[0] ?? '') +
        (Array.from(parts[parts.length - 1])[0] ?? '')
    ).toUpperCase();
}

function nodeRadius(followersCount: number, base: number): number {
    return base + Math.min(12, Math.log10(followersCount + 1) * 6);
}

export function FollowersNetworkGraph({
    user,
    nodes,
    edges,
    filter,
}: {
    user: User;
    nodes: FollowerGraphNode[];
    edges: FollowerGraphEdge[];
    filter: FollowerGraphFilter;
}) {
    const frameRef = useRef<HTMLDivElement>(null);
    const svgRef = useRef<SVGSVGElement>(null);
    const [hoveredId, setHoveredId] = useState<string | null>(null);
    const [view, setView] = useState({ x: 0, y: 0, k: 1 });
    const panning = useRef<{
        startX: number;
        startY: number;
        x: number;
        y: number;
    } | null>(null);

    const base = nodes.length > 120 ? 11 : 15;

    const placed = useMemo<PlacedNode[]>(() => {
        const sorted = [...nodes].sort(
            (a, b) => b.followers_count - a.followers_count,
        );
        const spread = WORLD - 90;
        const scale =
            sorted.length <= 1 ? 0 : spread / Math.sqrt(sorted.length);

        return sorted.map((node, index) => {
            const radius = nodeRadius(node.followers_count, base);
            const distance = 150 + scale * Math.sqrt(index);
            const angle = index * GOLDEN_ANGLE;

            return {
                ...node,
                radius,
                x: Math.cos(angle) * distance,
                y: Math.sin(angle) * distance,
            };
        });
    }, [nodes, base]);

    const byId = useMemo(
        () => new Map(placed.map((node) => [node.id, node])),
        [placed],
    );

    const neighbors = useMemo(() => {
        const map = new Map<string, Set<string>>();

        for (const edge of edges) {
            if (!byId.has(edge.source) || !byId.has(edge.target)) {
                continue;
            }

            if (!map.has(edge.source)) {
                map.set(edge.source, new Set());
            }

            if (!map.has(edge.target)) {
                map.set(edge.target, new Set());
            }

            map.get(edge.source)?.add(edge.target);
            map.get(edge.target)?.add(edge.source);
        }

        return map;
    }, [edges, byId]);

    const hovered =
        hoveredId === user.id
            ? null
            : hoveredId
              ? byId.get(hoveredId)
              : undefined;
    const highlighted = useMemo(() => {
        if (!hovered) {
            return null;
        }

        return new Set([
            hovered.id,
            ...(neighbors.get(hovered.id) ?? []),
            user.id,
        ]);
    }, [hovered, neighbors, user.id]);

    const matching = useMemo(() => {
        if (filter === 'everyone') {
            return null;
        }

        const wantBack = filter === 'followed-back';

        return new Set(
            placed
                .filter((node) => node.followed_back === wantBack)
                .map((node) => node.id),
        );
    }, [placed, filter]);

    const visibleEdges = useMemo(
        () =>
            edges.filter((edge) => {
                if (!byId.has(edge.source) || !byId.has(edge.target)) {
                    return false;
                }

                if (highlighted) {
                    return (
                        highlighted.has(edge.source) &&
                        highlighted.has(edge.target)
                    );
                }

                if (matching) {
                    return (
                        matching.has(edge.source) && matching.has(edge.target)
                    );
                }

                return true;
            }),
        [edges, byId, highlighted, matching],
    );

    const nodeOpacity = (nodeId: string) => {
        if (highlighted) {
            if (!highlighted.has(nodeId)) {
                return 0.06;
            }

            return matching === null || matching.has(nodeId) ? 1 : 0.35;
        }

        return matching === null || matching.has(nodeId) ? 1 : 0.12;
    };

    const zoom = (factor: number) => {
        setView((current) => ({
            ...current,
            k: Math.min(4, Math.max(0.5, current.k * factor)),
        }));
    };

    const reset = () => {
        setView({ x: 0, y: 0, k: 1 });
    };

    // React attaches wheel listeners as passive, so zooming without scrolling
    // the page needs a native non-passive listener.
    useEffect(() => {
        const svg = svgRef.current;

        if (!svg) {
            return;
        }

        const onWheel = (event: WheelEvent) => {
            event.preventDefault();
            zoom(event.deltaY < 0 ? 1.1 : 1 / 1.1);
        };

        svg.addEventListener('wheel', onWheel, { passive: false });

        return () => {
            svg.removeEventListener('wheel', onWheel);
        };
    }, []);

    return (
        <div ref={frameRef} className="relative overflow-hidden bg-card">
            <svg
                ref={svgRef}
                role="img"
                aria-label={`Network graph of the ${nodes.length} followers of @${user.username}`}
                viewBox={`${-WORLD} ${-WORLD} ${WORLD * 2} ${WORLD * 2}`}
                className="block h-[62vh] min-h-[26rem] w-full cursor-grab touch-none active:cursor-grabbing"
                onPointerDown={(event) => {
                    (event.target as Element).setPointerCapture?.(
                        event.pointerId,
                    );
                    panning.current = {
                        startX: event.clientX,
                        startY: event.clientY,
                        x: view.x,
                        y: view.y,
                    };
                }}
                onPointerMove={(event) => {
                    const pan = panning.current;

                    if (!pan) {
                        return;
                    }

                    const frame = frameRef.current?.getBoundingClientRect();
                    const scale = frame
                        ? (WORLD * 2) /
                          Math.min(frame.width, frame.height) /
                          view.k
                        : 1;

                    setView((current) => ({
                        ...current,
                        x: pan.x + (event.clientX - pan.startX) * scale,
                        y: pan.y + (event.clientY - pan.startY) * scale,
                    }));
                }}
                onPointerUp={() => {
                    panning.current = null;
                }}
                onPointerLeave={() => {
                    panning.current = null;
                    setHoveredId(null);
                }}
            >
                <g
                    transform={`translate(${view.x} ${view.y}) scale(${view.k})`}
                >
                    {placed.map((node) => {
                        const matches =
                            matching === null || matching.has(node.id);

                        return (
                            <line
                                key={`spoke-${node.id}`}
                                x1={0}
                                y1={0}
                                x2={node.x}
                                y2={node.y}
                                stroke="var(--border)"
                                strokeWidth={hovered?.id === node.id ? 1.6 : 1}
                                opacity={
                                    highlighted
                                        ? highlighted.has(node.id)
                                            ? 0.9
                                            : 0.06
                                        : matches
                                          ? 0.55
                                          : 0.08
                                }
                            />
                        );
                    })}

                    {visibleEdges.map((edge) => {
                        const source = byId.get(edge.source);
                        const target = byId.get(edge.target);

                        if (!source || !target) {
                            return null;
                        }

                        return (
                            <line
                                key={`${edge.source}-${edge.target}`}
                                x1={source.x}
                                y1={source.y}
                                x2={target.x}
                                y2={target.y}
                                stroke="var(--primary)"
                                strokeWidth={1.4}
                                opacity={highlighted ? 0.9 : 0.28}
                            />
                        );
                    })}

                    <CenterNode
                        user={user}
                        dimmed={
                            highlighted !== null && !highlighted.has(user.id)
                        }
                        onEnter={() => setHoveredId(user.id)}
                        onLeave={() => setHoveredId(null)}
                    />

                    {placed.map((node) => {
                        const lane = laneColor(node.lane);
                        const ringWidth = node.followed_back
                            ? hovered?.id === node.id
                                ? 3.5
                                : 2.75
                            : hovered?.id === node.id
                              ? 2
                              : 1.25;

                        return (
                            <g
                                key={node.id}
                                transform={`translate(${node.x} ${node.y})`}
                                opacity={nodeOpacity(node.id)}
                                className="cursor-pointer"
                                onPointerEnter={() => setHoveredId(node.id)}
                                onPointerLeave={() => setHoveredId(null)}
                                onClick={() => router.visit(toUrl(show(node)))}
                            >
                                <title>
                                    {node.name} (@{node.username})
                                    {node.followed_back
                                        ? ' — followed back'
                                        : ' — not followed back'}
                                </title>
                                <circle
                                    r={node.radius + 3}
                                    fill="none"
                                    stroke={lane}
                                    strokeWidth={ringWidth}
                                    strokeDasharray={
                                        node.followed_back ? undefined : '4 3'
                                    }
                                    opacity={node.followed_back ? 1 : 0.6}
                                />
                                <clipPath id={`fgn-${node.id}`}>
                                    <circle r={node.radius} />
                                </clipPath>
                                <circle r={node.radius} fill="var(--muted)" />
                                {node.avatar_url ? (
                                    <image
                                        href={node.avatar_url}
                                        x={-node.radius}
                                        y={-node.radius}
                                        width={node.radius * 2}
                                        height={node.radius * 2}
                                        clipPath={`url(#fgn-${node.id})`}
                                        preserveAspectRatio="xMidYMid slice"
                                    />
                                ) : (
                                    <text
                                        textAnchor="middle"
                                        dominantBaseline="central"
                                        fontSize={node.radius * 0.8}
                                        fill="var(--muted-foreground)"
                                        fontFamily="monospace"
                                    >
                                        {initialsOf(node.name)}
                                    </text>
                                )}
                                {(hovered?.id === node.id || view.k > 1.7) && (
                                    <text
                                        y={node.radius + 14}
                                        textAnchor="middle"
                                        fontSize={
                                            12 / Math.max(1, view.k * 0.7)
                                        }
                                        fill="var(--foreground)"
                                        fontFamily="monospace"
                                    >
                                        @{node.username}
                                    </text>
                                )}
                            </g>
                        );
                    })}
                </g>
            </svg>

            <div className="absolute top-3 right-3 flex gap-1">
                {[
                    {
                        title: 'Zoom in',
                        onClick: () => zoom(1.25),
                        Icon: ZoomIn,
                    },
                    {
                        title: 'Zoom out',
                        onClick: () => zoom(1 / 1.25),
                        Icon: ZoomOut,
                    },
                    { title: 'Reset view', onClick: reset, Icon: Maximize },
                ].map(({ title, onClick, Icon }) => (
                    <button
                        key={title}
                        type="button"
                        title={title}
                        aria-label={title}
                        onClick={onClick}
                        className="rounded-md border bg-background/90 p-2 text-muted-foreground shadow-sm transition hover:text-foreground"
                    >
                        <Icon className="size-4" />
                    </button>
                ))}
            </div>

            {hovered && (
                <div className="pointer-events-none absolute bottom-3 left-3 flex items-center gap-2 rounded-lg border bg-background/95 px-3 py-2 shadow-md">
                    <span
                        aria-hidden
                        className="size-2.5 shrink-0 rounded-full"
                        style={{ background: laneColor(hovered.lane) }}
                    />
                    <p className="text-sm">
                        <span className="font-medium">{hovered.name}</span>{' '}
                        <span className="font-mono text-muted-foreground">
                            @{hovered.username}
                        </span>
                    </p>
                    <p className="text-xs text-muted-foreground">
                        {hovered.followed_back ? (
                            <span className="font-medium text-primary">
                                Followed back
                            </span>
                        ) : (
                            'Not followed back'
                        )}
                        {' · '}
                        {(neighbors.get(hovered.id)?.size ?? 0) === 1
                            ? '1 connection here'
                            : `${neighbors.get(hovered.id)?.size ?? 0} connections here`}
                    </p>
                </div>
            )}

            <ul className="sr-only">
                {placed.map((node) => (
                    <li key={node.id}>
                        <a href={toUrl(show(node))}>
                            {node.name} (@{node.username})
                        </a>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function CenterNode({
    user,
    dimmed,
    onEnter,
    onLeave,
}: {
    user: User;
    dimmed: boolean;
    onEnter: () => void;
    onLeave: () => void;
}) {
    const lane = laneColor(user.lane);

    return (
        <g
            transform="translate(0 0)"
            opacity={dimmed ? 0.25 : 1}
            onPointerEnter={onEnter}
            onPointerLeave={onLeave}
            className={cn('cursor-pointer')}
            onClick={() => router.visit(toUrl(show(user)))}
        >
            <title>
                {user.name} (@{user.username})
            </title>
            <circle
                r={52}
                fill="none"
                stroke={lane}
                strokeWidth={3}
                strokeDasharray="6 5"
            />
            <clipPath id={`fgn-${user.id}`}>
                <circle r={44} />
            </clipPath>
            <circle r={44} fill="var(--muted)" />
            {user.avatar_url ? (
                <image
                    href={user.avatar_url}
                    x={-44}
                    y={-44}
                    width={88}
                    height={88}
                    clipPath={`url(#fgn-${user.id})`}
                    preserveAspectRatio="xMidYMid slice"
                />
            ) : (
                <text
                    textAnchor="middle"
                    dominantBaseline="central"
                    fontSize={30}
                    fill="var(--muted-foreground)"
                    fontFamily="monospace"
                >
                    {initialsOf(user.name)}
                </text>
            )}
            <text
                y={62}
                textAnchor="middle"
                fontSize={14}
                fontWeight={600}
                fill="var(--foreground)"
                fontFamily="monospace"
            >
                @{user.username}
            </text>
        </g>
    );
}
