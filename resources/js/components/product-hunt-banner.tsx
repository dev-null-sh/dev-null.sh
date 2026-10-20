import { ArrowBigUp } from 'lucide-react';
import { useEffect, useState } from 'react';

const PRODUCT_HUNT_URL = 'https://www.producthunt.com/products/dev-null';

/**
 * The launch goes live at 12:01 AM PDT on October 20th, and the strip stays up for its 24 hours, counting them down,
 * then quietly stops rendering. A tab left open across either moment picks it up within a second.
 */
const LAUNCH_STARTS_AT = Date.parse('2026-10-20T00:01:00-07:00');
const LAUNCH_ENDS_AT = LAUNCH_STARTS_AT + 24 * 60 * 60 * 1000;

const LANES = [0, 1, 2, 3, 4, 5].map((lane) => `var(--lane-${lane})`);

/**
 * On the terminal graphite in both themes (the `dark` class rescopes the theme's colors), with every lane running
 * underneath.
 */
export function ProductHuntBanner() {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, []);

    if (now < LAUNCH_STARTS_AT || now >= LAUNCH_ENDS_AT) {
        return null;
    }

    const remaining = LAUNCH_ENDS_AT - now;

    return (
        <a
            href={PRODUCT_HUNT_URL}
            target="_blank"
            rel="noopener noreferrer"
            className="dark group relative block bg-(--background) text-(--foreground)"
            data-test="product-hunt-banner"
        >
            <div className="mx-auto flex max-w-[1264px] items-center justify-center gap-3 px-4 py-2.5 text-sm">
                <span className="min-w-0">
                    We&rsquo;re{' '}
                    <span className="font-medium text-(--lane-1)">live</span> on{' '}
                    <span className="font-medium text-(--lane-0)">
                        Product Hunt
                    </span>
                    .{' '}
                    <span className="hidden text-(--muted-foreground) sm:inline">
                        Upvote now.
                    </span>
                </span>

                <span className="inline-flex shrink-0 items-center gap-1 rounded-md bg-(--lane-0) px-2.5 py-1 text-[13px] font-semibold text-(--background) transition-transform group-hover:-translate-y-0.5">
                    <ArrowBigUp className="size-4 fill-current" />
                    Upvote
                </span>

                <span
                    className="hidden font-mono text-[13px] text-(--muted-foreground) tabular-nums lg:inline"
                    suppressHydrationWarning
                >
                    {formatRemaining(remaining)} left
                </span>
            </div>

            <div aria-hidden className="flex h-0.5">
                {LANES.map((lane) => (
                    <span
                        key={lane}
                        className="flex-1"
                        style={{ backgroundColor: lane }}
                    />
                ))}
            </div>
        </a>
    );
}

function formatRemaining(milliseconds: number): string {
    const seconds = Math.floor(milliseconds / 1000);

    return [
        Math.floor(seconds / 3600),
        Math.floor((seconds % 3600) / 60),
        seconds % 60,
    ]
        .map((part) => String(part).padStart(2, '0'))
        .join(':');
}
