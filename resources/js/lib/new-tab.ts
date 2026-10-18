import type { MouseEvent } from 'react';

// Links, buttons, and fields keep their own middle click: an image still opens its photo, a mention its profile.
const controls =
    'a, button, input, textarea, select, label, summary, video, audio, [contenteditable]';

/**
 * A middle click on a commit, or on a reply inside it, opens it in a new tab, like a middle click on a link does. Only
 * the card closest to the click answers, so a reply opens itself rather than the commit around it.
 */
export function middleClickedPermalink(
    button: number,
    target: Element,
    card: HTMLElement,
): string | null {
    if (button !== 1 || target.closest('[data-permalink]') !== card) {
        return null;
    }

    const control = target.closest(controls);

    if (control && card.contains(control)) {
        return null;
    }

    return card.dataset.permalink ?? null;
}

export const openInNewTabOnMiddleClick = {
    // A middle click outside a link starts autoscrolling on Windows, which would follow the new tab around.
    onMouseDown: (event: MouseEvent<HTMLElement>) => {
        if (
            middleClickedPermalink(
                event.button,
                event.target as Element,
                event.currentTarget,
            )
        ) {
            event.preventDefault();
        }
    },
    onAuxClick: (event: MouseEvent<HTMLElement>) => {
        const permalink = middleClickedPermalink(
            event.button,
            event.target as Element,
            event.currentTarget,
        );

        if (permalink) {
            event.preventDefault();
            window.open(permalink, '_blank');
        }
    },
};
