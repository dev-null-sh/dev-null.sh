import { useRef, useState } from 'react';

/**
 * A choice that shows at once while the request for it runs, then gives way to what the server answered. `pick` returns
 * the request's `onFinish`, and only the latest pick's request clears it, so overlapping picks never flick back.
 */
export function usePicked<T>(): {
    picked: T | null;
    pick: (value: T) => () => void;
} {
    const [picked, setPicked] = useState<T | null>(null);
    const latest = useRef(0);

    const pick = (value: T) => {
        const id = ++latest.current;

        setPicked(value);

        return () => {
            if (id === latest.current) {
                setPicked(null);
            }
        };
    };

    return { picked, pick };
}
