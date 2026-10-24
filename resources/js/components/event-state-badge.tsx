import { CalendarCheck2, CalendarClock, CalendarX2, Radio } from 'lucide-react';
import { eventStateLabels } from '@/lib/event';
import { cn } from '@/lib/utils';
import type { EventState } from '@/types';

const styles: Record<EventState, string> = {
    upcoming:
        'border-emerald-600/30 bg-emerald-600/10 text-emerald-700 dark:text-emerald-400',
    live: 'border-red-600/30 bg-red-600/10 text-red-700 dark:text-red-400',
    ended: 'border-violet-600/30 bg-violet-600/10 text-violet-700 dark:text-violet-400',
    cancelled: 'border-border bg-muted text-muted-foreground',
};

const icons = {
    upcoming: CalendarClock,
    live: Radio,
    ended: CalendarCheck2,
    cancelled: CalendarX2,
} satisfies Record<EventState, unknown>;

/**
 * Like an issue's Open and Closed badge: the one place an event's state is spelled out.
 */
export function EventStateBadge({
    state,
    size = 'default',
    className,
}: {
    state: EventState;
    size?: 'default' | 'small';
    className?: string;
}) {
    const Icon = icons[state];

    return (
        <span
            className={cn(
                'inline-flex shrink-0 items-center gap-1 rounded-md border font-medium',
                size === 'small'
                    ? 'px-1.5 py-0.5 text-xs'
                    : 'px-2.5 py-1 text-sm',
                styles[state],
                className,
            )}
            data-test="event-state"
        >
            <Icon className={size === 'small' ? 'size-3' : 'size-4'} />
            {eventStateLabels[state]}
        </span>
    );
}
