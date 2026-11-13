import { Ban, Gavel, Hourglass } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { formatFullDateTime } from '@/lib/utils';

export type UserModerationHistory = {
    timeoutsCount: number;
    bansCount: number;
    timeouts: {
        id: string;
        reason: string;
        duration: string;
        startedAt: string;
        endsAt: string;
    }[];
    bans: {
        id: string;
        reason: string;
        bannedAt: string;
    }[];
};

export function UserModerationHistory({
    history,
}: {
    history: UserModerationHistory;
}) {
    const [open, setOpen] = useState(false);
    const timeoutLabel = `${history.timeoutsCount} ${history.timeoutsCount === 1 ? 'timeout' : 'timeouts'}`;
    const banLabel = `${history.bansCount} ${history.bansCount === 1 ? 'ban' : 'bans'}`;

    return (
        <>
            <Button
                type="button"
                variant="ghost"
                className="mt-3 h-auto min-h-8 justify-start gap-2 px-2 py-1.5 text-sm text-muted-foreground"
                onClick={() => setOpen(true)}
                aria-label="View moderation history"
                data-test="moderation-history-trigger"
            >
                <Gavel className="size-4" aria-hidden />
                <span>
                    {timeoutLabel} · {banLabel}
                </span>
            </Button>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="max-h-[calc(var(--visual-viewport-height,100dvh)-2rem)] overflow-y-auto sm:max-w-lg">
                    <DialogTitle>Moderation history</DialogTitle>
                    <DialogDescription>
                        Only you can see this record of timeouts and bans on
                        your account.
                    </DialogDescription>

                    <div className="-mx-1 mt-2 divide-y rounded-md border px-3">
                        <section
                            className="py-3"
                            aria-labelledby="timeouts-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2
                                    id="timeouts-heading"
                                    className="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Hourglass
                                        className="size-4 text-amber-600 dark:text-amber-400"
                                        aria-hidden
                                    />
                                    Timeouts
                                </h2>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {history.timeoutsCount}
                                </span>
                            </div>
                            {history.timeouts.length > 0 ? (
                                <ul className="mt-2 divide-y">
                                    {history.timeouts.map((timeout) => (
                                        <li
                                            key={timeout.id}
                                            className="py-3 first:pt-2 last:pb-1"
                                        >
                                            <div className="flex items-start justify-between gap-3">
                                                <p className="text-sm font-medium">
                                                    {timeout.reason}
                                                </p>
                                                <span className="shrink-0 font-mono text-xs text-muted-foreground">
                                                    {timeout.duration}
                                                </span>
                                            </div>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatFullDateTime(
                                                    timeout.startedAt,
                                                )}{' '}
                                                →{' '}
                                                {formatFullDateTime(
                                                    timeout.endsAt,
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    No timeouts recorded.
                                </p>
                            )}
                        </section>

                        <section
                            className="py-3"
                            aria-labelledby="bans-heading"
                        >
                            <div className="flex items-center justify-between gap-3">
                                <h2
                                    id="bans-heading"
                                    className="flex items-center gap-2 text-sm font-semibold"
                                >
                                    <Ban
                                        className="size-4 text-destructive"
                                        aria-hidden
                                    />
                                    Bans
                                </h2>
                                <span className="font-mono text-xs text-muted-foreground">
                                    {history.bansCount}
                                </span>
                            </div>
                            {history.bans.length > 0 ? (
                                <ul className="mt-2 divide-y">
                                    {history.bans.map((ban) => (
                                        <li
                                            key={ban.id}
                                            className="py-3 first:pt-2 last:pb-1"
                                        >
                                            <p className="text-sm font-medium">
                                                {ban.reason}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {formatFullDateTime(
                                                    ban.bannedAt,
                                                )}
                                            </p>
                                        </li>
                                    ))}
                                </ul>
                            ) : (
                                <p className="mt-2 text-sm text-muted-foreground">
                                    No bans recorded.
                                </p>
                            )}
                        </section>
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
