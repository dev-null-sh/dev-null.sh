import { ArrowUpRight } from 'lucide-react';

const videoId = 'nfmVdXZfzUE';
const videoUrl = `https://www.youtube.com/watch?v=${videoId}`;

function LiveDot() {
    return (
        <span aria-hidden className="relative flex size-2">
            <span className="absolute inline-flex size-full rounded-full bg-red-500 opacity-75 motion-safe:animate-ping" />
            <span className="relative inline-flex size-2 rounded-full bg-red-500" />
        </span>
    );
}

export function LiveStreamBanner() {
    return (
        <a
            href={videoUrl}
            target="_blank"
            rel="noopener noreferrer"
            className="group flex items-center justify-center gap-2 border-b bg-card px-4 py-2 text-sm font-medium transition-colors hover:bg-accent"
        >
            <LiveDot />
            <span>
                Watch us build /dev/null right now,{' '}
                <span className="underline-offset-4 group-hover:underline">
                    live on YouTube
                </span>
            </span>
            <ArrowUpRight className="size-4 text-muted-foreground" />
        </a>
    );
}

export function LiveStreamPlayer({ className }: { className?: string }) {
    return (
        <div className={className}>
            <p className="mb-3 flex items-center gap-2 text-sm font-medium text-muted-foreground">
                <LiveDot />
                Watch us build it, live
            </p>
            <div className="aspect-video w-full overflow-hidden rounded-lg border bg-muted">
                <iframe
                    src={`https://www.youtube-nocookie.com/embed/${videoId}`}
                    title="Watch us build /dev/null live on YouTube"
                    allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                    referrerPolicy="strict-origin-when-cross-origin"
                    allowFullScreen
                    className="size-full"
                />
            </div>
        </div>
    );
}
