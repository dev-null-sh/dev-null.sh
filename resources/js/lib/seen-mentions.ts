import { clearMentions } from '@/lib/chat';
import type { OrgRef } from '@/types';

const seeingFor = 1_000;

/**
 * Clears the org's mentions once their messages have stayed on screen in the thread for a second, while the window is
 * in front, so scrolling past one on the way somewhere else keeps it in the bell. The pinned banner hides what is under
 * it, and its open panel hides the thread. Mentions seen together clear together, and a clear the server refused is not
 * sent again while the thread stays open.
 */
export function watchSeenMentions(
    scroller: HTMLElement,
    org: OrgRef,
): { track: (ids: number[]) => void; stop: () => void } {
    let bubbles: HTMLElement[] = [];
    let seen: number[] = [];
    const sent = new Set<number>();
    let timer: number | undefined;

    const idOf = (bubble: HTMLElement) => Number(bubble.dataset.messageId);
    const onScreen = (): number[] => {
        const banner = scroller.parentElement?.querySelector<HTMLElement>(
            '[data-pinned-banner]',
        );

        if (
            document.hidden ||
            !document.hasFocus() ||
            banner?.dataset.state === 'open'
        ) {
            return [];
        }

        // Zooming in shows only part of the page, so the thread counts only where it is on the screen.
        const box = scroller.getBoundingClientRect();
        const zoom = window.visualViewport;
        const view = zoom
            ? {
                  top: zoom.offsetTop,
                  bottom: zoom.offsetTop + zoom.height,
                  left: zoom.offsetLeft,
                  right: zoom.offsetLeft + zoom.width,
              }
            : box;
        const top = Math.max(
            box.top,
            view.top,
            banner?.getBoundingClientRect().bottom ?? box.top,
        );
        const bottom = Math.min(box.bottom, view.bottom);
        const left = Math.max(box.left, view.left);
        const right = Math.min(box.right, view.right);
        // A message larger than the screen never shows half of itself, so filling half the screen counts too.
        const isHalfShown = (
            from: number,
            to: number,
            start: number,
            end: number,
        ) =>
            to > from &&
            end > start &&
            Math.min(to, end) - Math.max(from, start) >=
                Math.min(to - from, end - start) / 2;

        const ids = bubbles
            .filter((bubble) => {
                const rect = bubble.getBoundingClientRect();

                return (
                    bubble.isConnected &&
                    isHalfShown(rect.top, rect.bottom, top, bottom) &&
                    isHalfShown(rect.left, rect.right, left, right)
                );
            })
            .map(idOf)
            .filter((id) => !sent.has(id));

        return [...new Set(ids)];
    };
    const pause = () => {
        window.clearTimeout(timer);
        timer = undefined;
    };
    const update = () => {
        const shown = onScreen();

        if (shown.length === 0) {
            pause();
        } else if (shown.some((id) => !seen.includes(id))) {
            pause();
            timer = window.setTimeout(() => {
                timer = undefined;
                const ids = onScreen().filter((id) => seen.includes(id));
                seen = [];

                if (ids.length > 0) {
                    ids.forEach((id) => sent.add(id));
                    void clearMentions(org, ids).catch(() => undefined);
                }
            }, seeingFor);
        }

        seen = shown;
    };
    const blur = () => {
        pause();
        seen = [];
    };

    // Opening the pinned panel hides the thread, and the banner comes and goes with the pins, without any scrolling.
    const covers = new MutationObserver(update);

    if (scroller.parentElement !== null) {
        covers.observe(scroller.parentElement, {
            childList: true,
            subtree: true,
            attributeFilter: ['data-state'],
        });
    }

    scroller.addEventListener('scroll', update, { passive: true });
    window.addEventListener('resize', update);
    window.visualViewport?.addEventListener('resize', update);
    window.visualViewport?.addEventListener('scroll', update);
    window.addEventListener('focus', update);
    window.addEventListener('blur', blur);
    document.addEventListener('visibilitychange', update);

    return {
        track(ids) {
            bubbles = [
                ...scroller.querySelectorAll<HTMLElement>('[data-message-id]'),
            ].filter((bubble) => ids.includes(idOf(bubble)));
            update();
        },
        stop() {
            pause();
            covers.disconnect();
            scroller.removeEventListener('scroll', update);
            window.removeEventListener('resize', update);
            window.visualViewport?.removeEventListener('resize', update);
            window.visualViewport?.removeEventListener('scroll', update);
            window.removeEventListener('focus', update);
            window.removeEventListener('blur', blur);
            document.removeEventListener('visibilitychange', update);
        },
    };
}
