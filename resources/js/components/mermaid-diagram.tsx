import {
    Check,
    Code2,
    Copy,
    Image,
    GitBranch,
    ArrowLeftRight,
    Minus,
    Plus,
    RotateCcw,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { useClipboard } from '@/hooks/use-clipboard';
import { useMediaQuery } from '@/hooks/use-media-query';
import { MAX_DIAGRAM_LENGTH, renderMermaid } from '@/lib/mermaid-renderer';
import type { DiagramTheme } from '@/lib/mermaid-theme';

function useDiagramTheme(): DiagramTheme {
    const [theme, setTheme] = useState<DiagramTheme>('light');

    useEffect(() => {
        const update = () =>
            setTheme(
                document.documentElement.classList.contains('dark')
                    ? 'dark'
                    : 'light',
            );
        update();

        const observer = new MutationObserver(update);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });

        return () => observer.disconnect();
    }, []);

    return theme;
}

/**
 * Existing code-block geometry and colors, with an inert SVG image instead of injected SVG
 * markup. Invalid, large or unsupported diagrams keep their exact copyable source.
 */
export function MermaidDiagram({ source }: { source: string }) {
    const root = useRef<HTMLDivElement>(null);
    const [nearViewport, setNearViewport] = useState(false);
    const [view, setView] = useState<'diagram' | 'source'>('diagram');
    const [imageUrl, setImageUrl] = useState<string | null>(null);
    const [error, setError] = useState(false);
    const [zoom, setZoom] = useState(100);
    const [isWide, setIsWide] = useState(false);
    const isSmallScreen = useMediaQuery('(max-width: 639px)');
    const [copied, copy] = useClipboard();
    const theme = useDiagramTheme();
    const tooLarge = source.length > MAX_DIAGRAM_LENGTH;

    useEffect(() => {
        const element = root.current;

        if (!element) {
            return;
        }

        if (!('IntersectionObserver' in window)) {
            setNearViewport(true);

            return;
        }

        const observer = new IntersectionObserver(
            (entries) => {
                if (entries.some((entry) => entry.isIntersecting)) {
                    setNearViewport(true);
                    observer.disconnect();
                }
            },
            { rootMargin: '240px' },
        );

        observer.observe(element);

        return () => observer.disconnect();
    }, []);

    useEffect(() => {
        if (!nearViewport || tooLarge) {
            return;
        }

        let disposed = false;
        let url: string | null = null;

        setImageUrl(null);
        setError(false);

        void renderMermaid(source, theme)
            .then((svg) => {
                if (disposed) {
                    return;
                }

                // Horizontal charts must stay readable on phones instead of shrinking
                // their labels to a few pixels. Keep vertical charts fitted to the feed.
                const viewBox = svg
                    .match(/viewBox="([^"]+)"/)?.[1]
                    ?.trim()
                    .split(/[\s,]+/)
                    .map(Number);
                const width = viewBox?.[2] ?? 0;
                const height = viewBox?.[3] ?? 0;
                setIsWide(height > 0 && width / height > 1.6);

                url = URL.createObjectURL(
                    new Blob([svg], { type: 'image/svg+xml' }),
                );
                setImageUrl(url);
            })
            .catch(() => {
                if (!disposed) {
                    setError(true);
                    setView('source');
                }
            });

        return () => {
            disposed = true;

            if (url) {
                URL.revokeObjectURL(url);
            }
        };
    }, [source, theme, nearViewport, tooLarge]);

    const copySource = () => {
        void copy(source);
    };

    const showSource = view === 'source' || error || tooLarge;
    const status = tooLarge
        ? 'Diagram exceeds the 6,000-character limit. Source is preserved.'
        : error
          ? 'This diagram could not be rendered. Source is preserved.'
          : 'Loading diagram…';

    return (
        <div
            ref={root}
            data-test="mermaid-diagram"
            className="my-2 min-w-0 overflow-hidden rounded-md border bg-muted/60"
        >
            <div className="flex h-8 items-center justify-between gap-2 border-b pr-1 pl-3">
                <span className="flex min-w-0 items-center gap-1.5 truncate font-mono text-[11px] text-muted-foreground select-none">
                    <GitBranch
                        className="size-3.5 shrink-0 text-primary"
                        aria-hidden
                    />
                    mermaid
                </span>
                <div className="flex shrink-0 items-center gap-0.5">
                    <button
                        type="button"
                        onClick={() =>
                            setView(showSource ? 'diagram' : 'source')
                        }
                        aria-label={showSource ? 'Show diagram' : 'Show source'}
                        aria-pressed={showSource}
                        disabled={error || tooLarge}
                        title={showSource ? 'Show diagram' : 'Show source'}
                        className="grid size-6 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring disabled:opacity-40"
                    >
                        {showSource ? (
                            <Image className="size-3.5" aria-hidden />
                        ) : (
                            <Code2 className="size-3.5" aria-hidden />
                        )}
                    </button>
                    <button
                        type="button"
                        onClick={copySource}
                        aria-label="Copy Mermaid source"
                        title="Copy Mermaid source"
                        className="grid size-6 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                    >
                        {copied ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : (
                            <Copy className="size-3.5" aria-hidden />
                        )}
                    </button>
                </div>
            </div>

            {showSource ? (
                <>
                    {(error || tooLarge) && (
                        <p
                            role="status"
                            className="border-b px-3 py-2 text-xs text-muted-foreground"
                        >
                            {status}
                        </p>
                    )}
                    <pre className="max-h-96 overflow-auto px-3 py-2.5 font-mono text-[13px] leading-normal whitespace-pre">
                        {source}
                    </pre>
                </>
            ) : imageUrl === null ? (
                <div
                    role="status"
                    className="flex min-h-40 items-center justify-center px-3 py-6 text-xs text-muted-foreground"
                >
                    {status}
                </div>
            ) : (
                <>
                    <div
                        role="region"
                        aria-label="Diagram viewport"
                        tabIndex={0}
                        className="max-h-[440px] min-h-32 overflow-auto overscroll-contain bg-card px-3 py-4 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring sm:px-4 sm:py-5"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle, color-mix(in srgb, var(--border) 55%, transparent) 0.8px, transparent 1px)',
                            backgroundSize: '20px 20px',
                        }}
                    >
                        {/* An SVG in an img has no script, DOM access or interactive links. */}
                        <img
                            src={imageUrl}
                            alt="Mermaid diagram. Use Show source to read its definition."
                            className="mx-auto block h-auto max-w-none"
                            style={{
                                width: `${zoom}%`,
                                minWidth:
                                    isWide && isSmallScreen
                                        ? `${Math.round((600 * zoom) / 100)}px`
                                        : undefined,
                            }}
                        />
                    </div>
                    <div className="flex h-8 items-center gap-0.5 border-t px-1 font-mono text-[11px] text-muted-foreground">
                        <span
                            className="flex items-center gap-1 pl-2 text-[10px] sm:hidden"
                            aria-hidden
                        >
                            {isWide && isSmallScreen && (
                                <>
                                    <ArrowLeftRight className="size-3" />
                                    Scroll to explore
                                </>
                            )}
                        </span>
                        <div className="ml-auto flex items-center gap-0.5">
                            <button
                                type="button"
                                aria-label="Zoom out"
                                disabled={zoom <= 50}
                                onClick={() =>
                                    setZoom((value) => Math.max(50, value - 25))
                                }
                                className="grid size-6 place-items-center rounded-md hover:bg-muted disabled:opacity-40"
                            >
                                <Minus className="size-3" aria-hidden />
                            </button>
                            <span className="w-10 text-center tabular-nums">
                                {zoom}%
                            </span>
                            <button
                                type="button"
                                aria-label="Zoom in"
                                disabled={zoom >= 200}
                                onClick={() =>
                                    setZoom((value) =>
                                        Math.min(200, value + 25),
                                    )
                                }
                                className="grid size-6 place-items-center rounded-md hover:bg-muted disabled:opacity-40"
                            >
                                <Plus className="size-3" aria-hidden />
                            </button>
                            <button
                                type="button"
                                aria-label="Reset zoom"
                                onClick={() => setZoom(100)}
                                className="grid size-6 place-items-center rounded-md hover:bg-muted"
                            >
                                <RotateCcw className="size-3" aria-hidden />
                            </button>
                        </div>
                    </div>
                </>
            )}
        </div>
    );
}
