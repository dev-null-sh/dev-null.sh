import { useCallback, useState } from 'react';

export type Leaving = {
    /** Whether to render it: shown, or still on its way out. */
    isRendered: boolean;
    /** Whether it is on its way out, once hidden, until `onLeft`. */
    isLeaving: boolean;
    /** Called once it has left, like when its collapse ends. */
    onLeft: () => void;
};

function prefersStillness(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Keeps what gets hidden around while it leaves, like replies folding away. Without motion, nothing would end its
 * leaving, so it goes right away.
 */
export function useLeaving(
    isShown: boolean,
    isStill: () => boolean = prefersStillness,
): Leaving {
    const [wasShown, setWasShown] = useState(isShown);
    const [isLeaving, setIsLeaving] = useState(false);

    if (wasShown !== isShown) {
        setWasShown(isShown);
        setIsLeaving(!isShown && !isStill());
    }

    const onLeft = useCallback(() => setIsLeaving(false), []);

    return {
        isRendered: isShown || isLeaving,
        isLeaving: !isShown && isLeaving,
        onLeft,
    };
}
