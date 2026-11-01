import { useLayoutEffect } from 'react';
import type { RefObject } from 'react';
import { lightFreshRows } from '@/lib/fresh-rows';

/**
 * Lights the rows whose `data-*` id, read from `dataset[key]`, is in `freshIds`, with the fresh treatment (`data-fresh`) whenever `rows` change.
 */
export function useFreshRows(
    container: RefObject<HTMLElement | null>,
    key: string,
    freshIds: RefObject<Set<string>>,
    rows: unknown,
): void {
    useLayoutEffect(() => {
        if (freshIds.current.size === 0) {
            return;
        }

        return lightFreshRows(container.current, key, freshIds);
    }, [container, key, freshIds, rows]);
}
