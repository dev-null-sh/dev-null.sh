import { useLang } from '@erag/lang-sync-inertia/react';
import type { Streaks } from '@/types';

export function ActivityStreaks({
    streaks,
    lane,
}: {
    streaks: Streaks;
    lane: string;
}) {
    const { __, transChoice } = useLang();

    if (streaks.longest === 0) {
        return null;
    }

    return (
        <p className="flex flex-wrap items-center gap-x-2 gap-y-1 border-b px-4 py-3 font-mono text-[13px] text-muted-foreground sm:px-6">
            <span>
                {__('users.streaks.longest')}{' '}
                <span className="font-medium text-foreground">
                    {streaks.longest}
                </span>{' '}
                {transChoice('users.streaks.days', streaks.longest)}
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
                            {__('users.streaks.current')}{' '}
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
