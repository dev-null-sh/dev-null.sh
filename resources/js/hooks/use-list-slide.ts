import { useLayoutEffect, useRef } from 'react';

/**
 * Slides a list's rows from where they were to where they are when rows are added or removed, and fades the new ones
 * in, so a polled or edited list moves with transforms instead of jumping. Rows are matched to keys by position.
 */
export function useListSlide<T extends HTMLElement>(keys: string[]) {
    const list = useRef<T>(null);
    const offsets = useRef(new Map<string, number>());
    const joined = keys.join('\n');

    useLayoutEffect(() => {
        const element = list.current;
        const rows = [...(element?.children ?? [])] as HTMLElement[];
        const current = joined.split('\n');
        const previous = offsets.current;
        const prefersReducedMotion = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        ).matches;
        const record = () => {
            offsets.current = new Map(
                rows.map((row, index) => [current[index], row.offsetTop]),
            );
        };

        if (previous.size > 0 && !prefersReducedMotion) {
            rows.forEach((row, index) => {
                const before = previous.get(current[index]);

                if (before === undefined) {
                    row.animate(
                        { opacity: [0, 1] },
                        { duration: 300, easing: 'ease-out' },
                    );
                } else if (before !== row.offsetTop) {
                    row.animate(
                        {
                            transform: [
                                `translateY(${before - row.offsetTop}px)`,
                                'none',
                            ],
                        },
                        { duration: 300, easing: 'ease-out' },
                    );
                }
            });
        }

        record();

        if (!element) {
            return;
        }

        const observer = new ResizeObserver(record);
        observer.observe(element);

        return () => observer.disconnect();
    }, [joined]);

    return list;
}
