import { useEffect, useLayoutEffect, useRef, useState } from 'react';

const PRODUCT_HUNT_URL = 'https://www.producthunt.com/products/dev-null';

/**
 * The launch goes live at 12:01 AM PDT on October 20th, and the strip stays up for 24 hours,
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
    const ref = useRef<HTMLAnchorElement>(null);
    const isLive = now >= LAUNCH_STARTS_AT && now < LAUNCH_ENDS_AT;

    useEffect(() => {
        const interval = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(interval);
    }, []);

    /**
     * The strip sits above the layout, so the sidebar gives up as much of it as is still on screen: all of it at the
     * top of the page, none once it has scrolled away, keeping the sidebar's bottom at the bottom of the viewport.
     */
    useLayoutEffect(() => {
        const banner = ref.current;

        if (banner === null) {
            return;
        }

        const root = document.documentElement;
        let visibleHeight = -1;
        const update = () => {
            const next = Math.max(0, banner.getBoundingClientRect().bottom);

            if (next !== visibleHeight) {
                visibleHeight = next;
                root.style.setProperty('--launch-banner-height', `${next}px`);
            }
        };

        update();
        window.addEventListener('scroll', update, { passive: true });
        window.addEventListener('resize', update);

        return () => {
            window.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
            root.style.removeProperty('--launch-banner-height');
        };
    }, [isLive]);

    if (!isLive) {
        return null;
    }

    return (
        <a
            ref={ref}
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
                    .
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
