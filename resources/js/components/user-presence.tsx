import { cn, formatPresence } from '@/lib/utils';

/**
 * The online status dot and label, shared by the profile page and the hover preview. A fixed width can be passed in
 * where the label shares a row, so switching between "Online" and "Active 23h ago" does not move what sits beside it.
 */
export function UserPresence({
    online,
    lastActivityAt,
    className,
}: {
    online: boolean;
    lastActivityAt: string | null;
    className?: string;
}) {
    return (
        <span
            data-test="user-presence"
            className={cn(
                'flex shrink-0 items-center gap-1.5 font-sans text-xs',
                className,
            )}
        >
            {online ? (
                <span aria-hidden className="relative flex size-2">
                    <span className="absolute inline-flex size-full rounded-full bg-emerald-500 opacity-75 motion-safe:animate-ping" />
                    <span className="relative inline-flex size-2 rounded-full bg-emerald-500" />
                </span>
            ) : (
                <span
                    aria-hidden
                    className="size-2 shrink-0 rounded-full bg-muted-foreground/40"
                />
            )}
            <span
                className={cn(
                    'whitespace-nowrap',
                    online &&
                        'font-medium text-emerald-600 dark:text-emerald-400',
                )}
            >
                {formatPresence(online, lastActivityAt)}
            </span>
        </span>
    );
}
