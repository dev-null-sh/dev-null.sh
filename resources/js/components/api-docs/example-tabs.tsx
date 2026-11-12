import { useId } from 'react';
import type { CSSProperties, KeyboardEvent } from 'react';
import { flushSync } from 'react-dom';
import {
    activeExample,
    chooseExample,
    useChosenExample,
} from '@/components/api-docs/example-choice';
import { CodeBlock } from '@/components/code-block';
import { cn, withViewTransition } from '@/lib/utils';
import type { ApiDocsExample } from '@/types';

/**
 * The tab a key moves to, as a tab list's keys do: the arrows round the ends, Home and End to the ends.
 */
export function tabKeyTarget(
    key: string,
    active: number,
    count: number,
): number | null {
    switch (key) {
        case 'ArrowRight':
            return (active + 1) % count;
        case 'ArrowLeft':
            return (active - 1 + count) % count;
        case 'Home':
            return 0;
        case 'End':
            return count - 1;
        default:
            return null;
    }
}

/**
 * Moves to the tab a key points at: picks it first, at once rather than after an animated transition, so the tab focus
 * lands on is already the selected one and a next key counts from it. Says whether the key moved anything.
 */
export function moveToExample(
    key: string,
    active: number,
    labels: string[],
    pick: (label: string) => void,
    focus: (index: number) => void,
): boolean {
    const next = tabKeyTarget(key, active, labels.length);
    const label = next === null ? undefined : labels[next];

    if (next === null || label === undefined) {
        return false;
    }

    pick(label);
    focus(next);

    return true;
}

/**
 * How to call an endpoint, one tab per client, the active one underlined in Commit Blue like the page tabs. Picking a
 * client picks it on every endpoint, and next time too.
 */
export function ExampleTabs({
    examples,
    holdHeight = true,
}: {
    examples: ApiDocsExample[];
    holdHeight?: boolean;
}) {
    const id = useId();
    // Each tab list on the page slides its own underline, so each needs a name of its own, as a CSS ident.
    const indicatorName = `example-tab-${id.replace(/[^\w-]/g, '')}`;
    const active = activeExample(
        examples.map((example) => example.label),
        useChosenExample(),
    );
    const setActive = (index: number) => {
        const label = examples[index]?.label;

        if (label !== undefined && index !== active) {
            void withViewTransition(() => chooseExample(label));
        }
    };

    const onKeyDown = (event: KeyboardEvent<HTMLDivElement>) => {
        const moved = moveToExample(
            event.key,
            active,
            examples.map((example) => example.label),
            (label) => flushSync(() => chooseExample(label)),
            (index) => document.getElementById(`${id}-tab-${index}`)?.focus(),
        );

        if (moved) {
            event.preventDefault();
        }
    };

    if (examples.length === 0) {
        return null;
    }

    return (
        <div className="min-w-0">
            <div
                role="tablist"
                aria-label="Request example"
                onKeyDown={onKeyDown}
                className="flex gap-5 border-b"
            >
                {examples.map((candidate, index) => (
                    <button
                        key={candidate.label}
                        id={`${id}-tab-${index}`}
                        type="button"
                        role="tab"
                        aria-selected={index === active}
                        aria-controls={`${id}-panel-${index}`}
                        tabIndex={index === active ? 0 : -1}
                        onClick={() => setActive(index)}
                        className={cn(
                            'relative min-h-9 py-2 text-[15px] font-medium text-muted-foreground transition-colors hover:text-foreground',
                            index === active && 'text-foreground',
                        )}
                    >
                        {candidate.label}
                        {index === active && (
                            <span
                                aria-hidden
                                className="absolute inset-x-0 -bottom-px h-[3px] rounded-t-sm bg-primary [view-transition-class:tab-indicator] in-place:[view-transition-name:var(--transition-name)]"
                                style={
                                    {
                                        '--transition-name': indicatorName,
                                    } as CSSProperties
                                }
                            />
                        )}
                    </button>
                ))}
            </div>
            {/* Where the page renders on the server, every example sits in the same cell, so the tallest sets the
                height and the server's first tab giving way to the reader's own client moves nothing. In a sticky
                column, the height follows the example shown, or the hidden ones would hold it far below. */}
            <div className={cn(holdHeight && 'grid')}>
                {examples.map((candidate, index) => {
                    const isActive = index === active;

                    return (
                        <div
                            key={candidate.label}
                            id={`${id}-panel-${index}`}
                            role="tabpanel"
                            tabIndex={isActive ? 0 : -1}
                            aria-labelledby={`${id}-tab-${index}`}
                            aria-hidden={isActive ? undefined : true}
                            inert={!isActive}
                            hidden={!holdHeight && !isActive}
                            className={cn(
                                'min-w-0',
                                holdHeight && 'col-start-1 row-start-1',
                                holdHeight && !isActive && 'invisible',
                            )}
                        >
                            <CodeBlock
                                html={candidate.html}
                                language={candidate.language}
                                filename={null}
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}
