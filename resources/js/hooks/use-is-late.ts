import { useEffect, useState } from 'react';

/**
 * Whether a wait has lasted long enough to be worth saying so, so a quick answer shows nothing in between.
 */
export function useIsLate(isWaiting: boolean, delay = 300): boolean {
    const [isLate, setIsLate] = useState(false);

    useEffect(() => {
        if (!isWaiting) {
            return;
        }

        const timer = window.setTimeout(() => setIsLate(true), delay);

        return () => {
            window.clearTimeout(timer);
            setIsLate(false);
        };
    }, [isWaiting, delay]);

    return isWaiting && isLate;
}
