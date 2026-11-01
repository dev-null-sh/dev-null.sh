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

        // They stay lit until they are on screen, then while nobody is touching the page, and fade soon after the pointer
        // starts moving. Rows that arrived while the reader was further down wait for them to scroll back up.
        let timer: ReturnType<typeof setTimeout> | undefined;
        let sightedAt = 0;
        let hurried = false;
        const visible = new Set<Element>();

        const settle = () => {
            elements.forEach((row) => {
                row.dataset.fresh = 'fading';
                setTimeout(() => {
                    if (row.dataset.fresh === 'fading') {
                        row.removeAttribute('data-fresh');
                    }
                }, 1400);
            });
            freshIds.current = new Set();
            cleanup();
        };

        const hurry = () => {
            if (hurried || sightedAt === 0 || Date.now() - sightedAt < 700) {
                return;
            }

            hurried = true;
            clearTimeout(timer);
            timer = setTimeout(settle, 1800);
        };

        const observer = new IntersectionObserver(
            (entries) => {
                entries.forEach((entry) =>
                    entry.isIntersecting
                        ? visible.add(entry.target)
                        : visible.delete(entry.target),
                );

                if (visible.size > 0 && sightedAt === 0) {
                    sightedAt = Date.now();
                    timer = setTimeout(settle, 7000);
                }
            },
            { threshold: 0.6 },
        );

        elements.forEach((row) => observer.observe(row));

        function cleanup() {
            clearTimeout(timer);
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
