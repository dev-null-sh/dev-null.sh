import { useLang } from '@erag/lang-sync-inertia/react';

const videoId = 'nfmVdXZfzUE';

function LiveDot() {
    return (
        <span aria-hidden className="relative flex size-2">
            <span className="absolute inline-flex size-full rounded-full bg-red-500 opacity-75 motion-safe:animate-ping" />
            <span className="relative inline-flex size-2 rounded-full bg-red-500" />
        </span>
    );
}

export function LiveStreamPlayer({ className }: { className?: string }) {
    const { __ } = useLang();

    return (
        <div className={className}>
            <p className="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <LiveDot />
                {__('users.live.heading')}
            </p>
            <div className="aspect-video w-full overflow-hidden rounded-lg border bg-muted">
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${videoId}`}
                    title={__('users.live.player_title')}
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerPolicy="strict-origin-when-cross-origin"
                    allowFullScreen
                    className="size-full"
                />
            </div>
        </div>
    );
}
