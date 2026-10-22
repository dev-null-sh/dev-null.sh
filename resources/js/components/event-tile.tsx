import { eventTile } from '@/lib/event-dates';
import { cn } from '@/lib/utils';

export function EventTile({
    date,
    timeZone,
    className,
}: {
    date: string;
    timeZone?: string;
    className?: string;
}) {
    const { month, day } = eventTile(date, timeZone);

    return (
        <span
            aria-hidden
            className={cn(
                'flex size-12 shrink-0 flex-col items-center justify-center overflow-hidden rounded-md border bg-card font-mono leading-none',
                className,
            )}
        >
            <span className="text-[10px] font-medium text-primary">
                {month}
            </span>
            <span className="mt-1 text-lg font-medium tabular-nums">{day}</span>
        </span>
    );
}
