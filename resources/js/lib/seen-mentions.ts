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

        const area = scroller.getBoundingClientRect();
        const top = Math.max(
            area.top,
            banner?.getBoundingClientRect().bottom ?? area.top,
        );

        // A message taller than the screen never shows half of itself, so filling half the screen counts too.
        const ids = bubbles
            .filter((bubble) => {
                const { top: from, bottom: to } =
                    bubble.getBoundingClientRect();
                const shown = Math.min(to, area.bottom) - Math.max(from, top);

                return (
                    bubble.isConnected &&
                    to > from &&
                    shown >= Math.min(to - from, area.bottom - top) / 2
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
            window.removeEventListener('focus', update);
            window.removeEventListener('blur', blur);
            document.removeEventListener('visibilitychange', update);
        },
    };
}
