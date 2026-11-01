import { useLayoutEffect } from 'react';
import type { RefObject } from 'react';

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
        const ids = freshIds.current;

        if (ids.size === 0) {
            return;
        }

        const elements = Array.from(
            container.current?.querySelectorAll<HTMLElement>(
                `[data-${key.replace(/[A-Z]/g, (letter) => `-${letter.toLowerCase()}`)}]`,
            ) ?? [],
        ).filter((row) => ids.has(row.dataset[key] ?? ''));
        elements
            .filter((row) => row.dataset.fresh === undefined)
            .forEach((row) => {
                row.dataset.fresh = '';
            });

        // Each row stays lit until it is on screen, then while nobody is touching the page, and fades soon after the
        // pointer starts moving. Rows further down wait for the reader to scroll to them.
        const timers = new Map<Element, ReturnType<typeof setTimeout>>();
        const sightedAt = new Map<Element, number>();
        const hurried = new Set<Element>();
        let unsettled = elements.length;

        const settle = (row: HTMLElement) => {
            hurried.add(row);
            row.dataset.fresh = 'fading';
            setTimeout(() => {
                if (row.dataset.fresh === 'fading') {
                    row.removeAttribute('data-fresh');
                }
            }, 1400);
            freshIds.current.delete(row.dataset[key] ?? '');

            if (--unsettled === 0) {
                cleanup();
            }
        };

        const settleIn = (row: HTMLElement, delay: number) => {
            clearTimeout(timers.get(row));
            timers.set(
                row,
                setTimeout(() => settle(row), delay),
            );
        };

        const hurry = () => {
            sightedAt.forEach((at, row) => {
                if (!hurried.has(row) && Date.now() - at >= 700) {
                    hurried.add(row);
                    settleIn(row as HTMLElement, 1800);
                }
            });
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries
                    .filter(
                        (entry) =>
                            entry.isIntersecting &&
                            !sightedAt.has(entry.target),
                    )
                    .forEach((entry) => {
                        sightedAt.set(entry.target, Date.now());
                        settleIn(entry.target as HTMLElement, 7000);
                    });
            },
            { threshold: 0.6 },
        );

        elements.forEach((row) => observer.observe(row));

        function cleanup() {
            timers.forEach((timer) => clearTimeout(timer));
            observer.disconnect();
            window.removeEventListener('pointermove', hurry);
            window.removeEventListener('wheel', hurry);
            window.removeEventListener('keydown', hurry);
        }

        window.addEventListener('pointermove', hurry, { passive: true });
        window.addEventListener('wheel', hurry, { passive: true });
        window.addEventListener('keydown', hurry);

        return cleanup;
    }, [container, key, freshIds, rows]);
}
