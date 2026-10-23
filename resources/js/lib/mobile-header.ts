const revealDistance = 48;
const hideDistance = 8;

export type MobileHeaderScroll = {
    y: number;
    isDown: boolean;
    turnedAt: number;
    isShown: boolean;
};

export const restingMobileHeader = (y: number): MobileHeaderScroll => ({
    y,
    isDown: true,
    turnedAt: y,
    isShown: false,
});

/**
 * The header comes back once scrolling up has gone a little way, so a finger settling mid-read leaves it hidden, and
 * goes again as soon as scrolling turns down. Where it sits in the flow at the top of the page, it is just there. More
 * than a screen at once is the page being moved, by history or an anchor, rather than scrolled, so it stays away.
 */
export function scrolledMobileHeader(
    previous: MobileHeaderScroll,
    y: number,
    isInFlow: boolean,
    screenHeight: number,
): MobileHeaderScroll {
    if (y === previous.y) {
        return previous;
    }

    if (Math.abs(y - previous.y) > screenHeight) {
        return restingMobileHeader(y);
    }

    const isDown = y > previous.y;
    const turnedAt =
        isDown === previous.isDown ? previous.turnedAt : previous.y;
    const distance = Math.abs(y - turnedAt);

    return {
        y,
        isDown,
        turnedAt,
        isShown:
            !isInFlow &&
            (isDown
                ? previous.isShown && distance <= hideDistance
                : previous.isShown || distance >= revealDistance),
    };
}
