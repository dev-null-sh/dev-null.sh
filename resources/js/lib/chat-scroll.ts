import type { Message } from '@/types';

const MIN_GLIDE_MS = 180;
const MAX_GLIDE_MS = 500;

/**
 * The scroll position of a glide to the present, eased out so the motion
 * settles instead of stopping dead. `start` is negative in the reversed
 * scroller, and `progress` runs from 0 to 1.
 */
export function presentOffset(start: number, progress: number): number {
    // Frame timestamps can predate the start time, and a late frame can overshoot 1.
    const clamped = Math.min(1, Math.max(0, progress));

    // `Math.round` can hand back `-0`, which callers should never see.
    return Math.round(start * (1 - clamped) ** 3) || 0;
}

/**
 * Long conversations should not turn the glide into a slow ride, so the
 * duration grows with the square root of the distance and then stops.
 */
export function glideDuration(distance: number): number {
    return Math.min(
        MAX_GLIDE_MS,
        MIN_GLIDE_MS + Math.sqrt(Math.abs(distance)) * 2.5,
    );
}

/**
 * Glides the scroller back to the present, returning a cancel function.
 * Respects the reduced-motion preference.
 */
export function glideToPresent(
    scroller: HTMLElement,
    options: { duration?: number; onDone?: () => void } = {},
): () => void {
    const start = scroller.scrollTop;

    if (start === 0) {
        options.onDone?.();

        return () => {};
    }

    if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
        scroller.scrollTop = 0;
        options.onDone?.();

        return () => {};
    }

    const duration = options.duration ?? glideDuration(start);
    const startedAt = performance.now();
    let frame = 0;

    const step = (now: number) => {
        // The frame timestamp can predate `startedAt`, which `presentOffset` clamps.
        const progress = Math.min(1, (now - startedAt) / duration);
        scroller.scrollTop = presentOffset(start, progress);

        if (progress < 1) {
            frame = requestAnimationFrame(step);

            return;
        }

        options.onDone?.();
    };

    frame = requestAnimationFrame(step);

    return () => cancelAnimationFrame(frame);
}

export function isBehindLatest(
    scroller: HTMLElement,
    messages: Message[],
): boolean {
    const boundary = messages.filter((message) => !message.event).at(-25);
    const bubble = boundary
        ? scroller.querySelector<HTMLElement>(
              `[data-scroll-anchor="${CSS.escape(boundary.client_id)}"]`,
          )
        : null;

    return (
        bubble !== null &&
        bubble.getBoundingClientRect().top >=
            scroller.getBoundingClientRect().bottom
    );
}
