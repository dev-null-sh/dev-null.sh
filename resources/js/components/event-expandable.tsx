import { ChevronDown } from 'lucide-react';
import type { ReactNode } from 'react';
import { useState } from 'react';
import { cn } from '@/lib/utils';

/**
 * Grid rows animate from 0fr to 1fr, so the height follows the content without measuring it.
 */
function Reveal({ open, children }: { open: boolean; children: ReactNode }) {
    return (
        <div
            className={cn(
                'grid transition-[grid-template-rows,opacity] duration-300 ease-out motion-reduce:transition-none',
                open
                    ? 'grid-rows-[1fr] opacity-100'
                    : 'grid-rows-[0fr] opacity-0',
            )}
            aria-hidden={!open}
            inert={!open}
        >
            <div className="min-h-0 overflow-hidden">{children}</div>
        </div>
    );
}

/**
 * The compact ticket opens into the full event in place; the full block is only mounted once it is first opened.
 */
export function EventExpandable({
    expanded,
    onExpand,
    title,
    ticket,
    details,
}: {
    expanded: boolean;
    onExpand: () => void;
    title: string;
    ticket: ReactNode;
    details: ReactNode;
}) {
    const [hasOpened, setHasOpened] = useState(expanded);

    return (
        <div>
            <Reveal open={!expanded}>
                <button
                    type="button"
                    aria-expanded={expanded}
                    aria-label={`Show details of ${title}`}
                    onClick={() => {
                        setHasOpened(true);
                        onExpand();
                    }}
                    className="group/ticket relative block w-full rounded-lg text-left transition-colors focus-visible:outline-none hover:[&>div]:bg-accent/40 focus-visible:[&>div]:ring-[3px] focus-visible:[&>div]:ring-ring/50 focus-visible:[&>div]:ring-inset"
                    data-test="event-ticket-toggle"
                >
                    {ticket}
                    <ChevronDown
                        aria-hidden
                        className="absolute right-2.5 bottom-2.5 size-4 text-muted-foreground transition-transform group-hover/ticket:translate-y-0.5"
                    />
                </button>
            </Reveal>
            <Reveal open={expanded}>{hasOpened && details}</Reveal>
        </div>
    );
}
