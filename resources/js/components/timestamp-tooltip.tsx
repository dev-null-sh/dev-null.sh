import { usePage } from '@inertiajs/react';
import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    browserTimeZone,
    formatZonedDateTime,
    timeZoneLabel,
} from '@/lib/utils';
import type { User } from '@/types';

export function TimestampTooltip({
    date,
    author,
    children,
}: {
    date: string;
    author: User;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent className="max-w-none">
                <ZonedTimes date={date} author={author} />
            </TooltipContent>
        </Tooltip>
    );
}

function ZonedTimes({ date, author }: { date: string; author: User }) {
    const { auth } = usePage().props;

    const rows = [
        { who: 'You', timeZone: auth.user.timezone ?? browserTimeZone() },
        ...(author.timezone !== null && author.id !== auth.user.id
            ? [{ who: `@${author.username}`, timeZone: author.timezone }]
            : []),
    ];

    return (
        <div
            className="grid grid-cols-[auto_auto] gap-x-3 gap-y-1"
            data-test="timestamp-tooltip"
        >
            {rows.map((row) => (
                <p key={row.who} className="col-span-2 grid grid-cols-subgrid">
                    <span className="tabular-nums">
                        {formatZonedDateTime(date, row.timeZone)}
                    </span>
                    <span className="text-primary-foreground/60">
                        {row.who} · {timeZoneLabel(date, row.timeZone)}
                    </span>
                </p>
            ))}
        </div>
    );
}
