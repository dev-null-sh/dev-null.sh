import { useCallback, useState } from 'react';
import { useMediaQuery } from '@/hooks/use-media-query';

export type Leaving = {
    /** Whether to render it: shown, or still on its way out. */
    isRendered: boolean;
    /** Whether it is on its way out, once hidden, until `onLeft`. */
    isLeaving: boolean;
    /** Called once it has left, like when its collapse ends. */
    onLeft: () => void;
};

/**
 * Keeps what gets hidden around while it leaves, like replies folding away. Without motion, nothing would end its
 * leaving, so it goes right away.
 */
export function useLeaving(isShown: boolean): Leaving {
    const isStill = useMediaQuery('(prefers-reduced-motion: reduce)');
    const [wasShown, setWasShown] = useState(isShown);
    const [isLeaving, setIsLeaving] = useState(false);

    if (wasShown !== isShown || (isLeaving && isStill)) {
        setWasShown(isShown);
        setIsLeaving(!isShown && !isStill);
    }

    const onLeft = useCallback(() => setIsLeaving(false), []);

    return {
        isRendered: isShown || isLeaving,
        isLeaving: !isShown && isLeaving,
        onLeft,
    };
}
