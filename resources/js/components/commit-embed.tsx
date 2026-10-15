import { Play, X } from 'lucide-react';
import { useLayoutEffect, useRef } from 'react';
import type { Embed } from '@/hooks/use-embed';
import { hostname } from '@/lib/utils';

/**
 * How far past the edge of the screen an open embed may scroll before it closes, as a share of the screen's height.
 */
const closeDistance = '50%';

/**
 * The frame keeps its own origin so the page can use its own storage, and it can never reach this page: no
 * scripting across origins, no navigating the top window, no popups. A page on our own origin gets no origin at all.
 */
function sandboxFor(url: string): string {
    const isOwnOrigin = new URL(url).origin === window.location.origin;

    return [
        'allow-scripts',
        'allow-forms',
        'allow-pointer-lock',
        ...(isOwnOrigin ? [] : ['allow-same-origin']),
    ].join(' ');
}

/**
 * Shows a banner until it is opened, then the embed. It closes again once it is scrolled well out of view, unless the
 * embed is fullscreen. Opening it closes any other embed, which may sit above it, so the page is scrolled to open the
 * embed right where its banner was.
 */
export function CommitEmbed({
    url,
    ratio,
    embed,
}: {
    url: string;
    ratio?: string | null;
    embed: Embed;
}) {
    const frameRef = useRef<HTMLDivElement>(null);
    const bannerTop = useRef<number | null>(null);
    const host = hostname(url);
    const [width, height] = (ratio ?? '1:1').split(':');
    const { isOpen, open, close, bannerRef } = embed;

    useLayoutEffect(() => {
        const frame = frameRef.current;

        if (!isOpen || !frame) {
            return;
        }

        if (bannerTop.current !== null) {
            window.scrollBy(
                0,
                frame.getBoundingClientRect().top - bannerTop.current,
            );
            bannerTop.current = null;
        }

        const observer = new IntersectionObserver(
            ([entry]) => {
                if (!entry.isIntersecting && !document.fullscreenElement) {
                    close();
                }
            },
            { rootMargin: `${closeDistance} 0px` },
        );

        observer.observe(frame);

        return () => observer.disconnect();
    }, [isOpen, close]);

    if (!isOpen) {
        return (
            <button
                ref={bannerRef}
                type="button"
                onClick={(event) => {
                    event.stopPropagation();
                    bannerTop.current =
                        event.currentTarget.getBoundingClientRect().top;
                    open();
                }}
                aria-label={`Load embed from ${host}`}
                className="mt-3 flex w-full items-center gap-2.5 rounded-lg border px-3 py-2 text-left transition-colors hover:bg-accent focus-visible:bg-accent focus-visible:outline-none"
                data-test="load-embed-button"
            >
                <span className="flex size-6 shrink-0 items-center justify-center rounded-full bg-primary text-primary-foreground">
                    <Play className="size-3 translate-x-px fill-current" />
                </span>
                <span className="shrink-0 text-sm font-medium">Load embed</span>
                <span className="ml-auto min-w-0 truncate font-mono text-[13px] text-muted-foreground">
                    {host}
                </span>
            </button>
        );
    }

    return (
        <div
            ref={frameRef}
            className="mt-3 overflow-hidden rounded-lg border"
            style={{
                aspectRatio: `${width} / ${height}`,
                width: `min(100%, 80svh * ${width} / ${height})`,
            }}
            data-test="commit-embed"
        >
            <iframe
                src={url}
                title={`Embed from ${host}`}
                sandbox={sandboxFor(url)}
                allow="fullscreen"
                className="size-full bg-background"
                data-test="commit-embed-frame"
            />
        </div>
    );
}

export function CloseEmbedButton({ embed }: { embed: Embed }) {
    if (!embed.isOpen) {
        return null;
    }

    return (
        <button
            type="button"
            onClick={(event) => {
                event.stopPropagation();
                embed.dismiss();
            }}
            aria-label="Close embed"
            title="Close embed"
            className="ml-auto flex items-center rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
            data-test="close-embed-button"
        >
            <X className="size-4" />
        </button>
    );
}
