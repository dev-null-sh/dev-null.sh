import type { Player } from 'asciinema-player';
import { useEffect, useRef, useState } from 'react';

export function AsciinemaStream({
    src,
    url,
    title,
    height,
}: {
    src: string;
    url: string;
    title: string;
    height: number;
}) {
    const container = useRef<HTMLDivElement>(null);
    const [status, setStatus] = useState<'loading' | 'ready' | 'error'>(
        'loading',
    );

    useEffect(() => {
        let cancelled = false;
        let player: Player | undefined;

        void Promise.all([
            import('asciinema-player'),
            import('asciinema-player/dist/bundle/asciinema-player.css'),
        ])
            .then(([asciinema]) => {
                if (cancelled || !container.current) {
                    return;
                }

                player = asciinema.create(src, container.current, {
                    autoPlay: true,
                    fit: 'width',
                    controls: true,
                });
                setStatus('ready');
            })
            .catch(() => {
                if (!cancelled) {
                    setStatus('error');
                }
            });

        return () => {
            cancelled = true;
            player?.dispose();
        };
    }, [src]);

    return (
        <div data-test="asciinema-stream">
            <div
                className="relative bg-black"
                style={status === 'ready' ? undefined : { height }}
            >
                <div
                    ref={container}
                    role="region"
                    aria-label={title}
                    className="w-full"
                    data-test="asciinema-stream-player"
                />
                {status !== 'ready' && (
                    <p
                        role="status"
                        className="absolute inset-0 flex items-center justify-center px-4 text-center text-sm text-white"
                    >
                        {status === 'error'
                            ? 'Could not load the live stream. Open it on asciinema below.'
                            : 'Connecting to live stream…'}
                    </p>
                )}
            </div>
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer nofollow ugc"
                className="flex items-center justify-between gap-3 border-t px-3 py-2 text-sm transition-colors hover:bg-accent focus-visible:bg-accent"
            >
                <span className="truncate">{title}</span>
                <span className="shrink-0 text-muted-foreground">
                    Open on asciinema
                </span>
            </a>
        </div>
    );
}
