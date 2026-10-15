import type { Streaks } from '@/types';

export function ActivityStreaks({
    streaks,
    lane,
}: {
    streaks: Streaks;
    lane: string;
}) {
    if (streaks.longest === 0) {
        return null;
    }

    return (
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 border-b px-4 py-3 font-mono text-[13px] text-muted-foreground sm:px-6">
            <span>
                longest streak{' '}
                <span className="font-medium text-foreground">
                    {streaks.longest}
                </span>{' '}
                {streaks.longest === 1 ? 'day' : 'days'}
            </span>

            {streaks.current > 0 && (
                <>
                    <span aria-hidden>·</span>
                    <span className="flex items-center gap-1.5">
                        <span
                            aria-hidden
                            className="size-2 rounded-full"
                            style={{ background: lane }}
                        />
                        <span>
                            current{' '}
                            <span className="font-medium text-foreground">
                                {streaks.current}
                            </span>
                        </span>
                    </span>
                </>
            )}
        </p>
    );
}
