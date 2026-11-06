import { clearMentions } from '@/lib/chat';
import type { OrgRef } from '@/types';

const seeingFor = 1_000;
const thresholds = Array.from({ length: 21 }, (_, step) => step / 20);

/**
 * Clears the org's mentions once their messages have stayed on screen in the thread for a second, while the window is
 * in front, so scrolling past one on the way somewhere else keeps it in the bell. Mentions seen together clear
 * together, and a clear the server refused is not sent again while the thread stays open.
 */
export function watchSeenMentions(
    scroller: HTMLElement,
    org: OrgRef,
): { track: (ids: number[]) => void; stop: () => void } {
    let tracked = new Set<number>();
    const observed = new Set<HTMLElement>();
    const inView = new Set<HTMLElement>();
    const sent = new Set<number>();
    let timer: number | undefined;

    const idOf = (element: HTMLElement) => Number(element.dataset.messageId);
    const seen = () => [
        ...new Set(
            [...inView]
                .map(idOf)
                .filter((id) => tracked.has(id) && !sent.has(id)),
        ),
    ];
    const restart = () => {
        window.clearTimeout(timer);
        timer = undefined;

        if (document.hidden || !document.hasFocus() || seen().length === 0) {
            return;
        }

        timer = window.setTimeout(() => {
            timer = undefined;
            const ids = seen();
            ids.forEach((id) => sent.add(id));
            void clearMentions(org, ids).catch(() => undefined);
        }, seeingFor);
    };
    const pause = () => {
        window.clearTimeout(timer);
        timer = undefined;
    };

    const observer = new IntersectionObserver(
        (entries) => {
            const before = seen();

            for (const entry of entries) {
                const element = entry.target as HTMLElement;
                const height = entry.rootBounds?.height ?? Infinity;

                // A message taller than the screen never shows half of itself, so filling half the screen counts too.
                if (
                    entry.isIntersecting &&
                    (entry.intersectionRatio >= 0.5 ||
                        entry.intersectionRect.height >= height / 2)
                ) {
                    inView.add(element);
                } else {
                    inView.delete(element);
                }
            }

            const after = seen();

            if (after.some((id) => !before.includes(id))) {
                restart();
            } else if (after.length === 0) {
                pause();
            }
        },
        { root: scroller, threshold: thresholds },
    );

    window.addEventListener('focus', restart);
    window.addEventListener('blur', pause);
    document.addEventListener('visibilitychange', restart);

    return {
        track(ids) {
            tracked = new Set(ids);

            for (const element of observed) {
                if (!tracked.has(idOf(element)) || !element.isConnected) {
                    observer.unobserve(element);
                    observed.delete(element);
                    inView.delete(element);
                }
            }

            for (const element of scroller.querySelectorAll<HTMLElement>(
                '[data-message-id]',
            )) {
                if (tracked.has(idOf(element)) && !observed.has(element)) {
                    observer.observe(element);
                    observed.add(element);
                }
            }

            if (seen().length === 0) {
                pause();
            }
        },
        stop() {
            pause();
            observer.disconnect();
            window.removeEventListener('focus', restart);
            window.removeEventListener('blur', pause);
            document.removeEventListener('visibilitychange', restart);
        },
    };
}
