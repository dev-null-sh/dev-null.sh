import { Check, Plus } from 'lucide-react';
import type { EventGoing } from '@/hooks/use-event-going';
import { cn } from '@/lib/utils';

export function EventGoingButton({
    going,
    className,
}: {
    going: EventGoing;
    className?: string;
}) {
    return (
        <button
            type="button"
            onClick={going.toggle}
            aria-pressed={going.isGoing}
            aria-label={`${going.isGoing ? 'Going' : 'Go'}, ${going.count} going`}
            className={cn(
                'inline-flex items-center gap-1 rounded-md px-2 py-0.5 text-[13px] leading-5 font-medium ring-1 transition-colors ring-inset',
                going.isGoing
                    ? 'bg-primary-soft text-primary ring-transparent'
                    : 'text-foreground ring-input hover:bg-accent',
                className,
            )}
            data-test="event-going-button"
        >
            {going.isGoing ? (
                <Check className="size-3.5 shrink-0" strokeWidth={2.5} />
            ) : (
                <Plus className="size-3.5 shrink-0" strokeWidth={2.5} />
            )}
            Going
            <span aria-hidden>·</span>
            <span className="font-mono">{going.count}</span>
        </button>
    );
}
