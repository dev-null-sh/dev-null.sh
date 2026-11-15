import { usePage } from '@inertiajs/react';
import { useNow } from '@/hooks/use-now';
import { useSharedState } from '@/hooks/use-shared-state';
import { cn } from '@/lib/utils';

const dailyPasteis = 10;

/**
 * What is left to give today, for someone with pastéis mode on, or null. It reads the same shared allowance as the
 * pastel buttons, so it goes down as soon as one is given, and the live count from the server when one goes out from
 * another device. Within a day the allowance only goes down, so the lower of the two is the right one.
 */
export function usePasteisRemaining(): number | null {
    const { auth } = usePage().props;
    const day = useNow().toISOString().slice(0, 10);
    const live = auth.pasteis?.day === day ? auth.pasteis.remaining : null;
    const [remaining] = useSharedState(
        `pasteis-allowance:${auth.user?.id}:${day}`,
        live ?? dailyPasteis,
    );

    if (!auth.pasteis || !auth.user) {
        return null;
    }

    return live === null ? remaining : Math.min(remaining, live);
}

/**
 * The day's pastéis still to give, which otherwise only shows in a pastel button's tooltip.
 */
export function PasteisBalance({
    isCompact = false,
    className,
}: {
    isCompact?: boolean;
    className?: string;
}) {
    const remaining = usePasteisRemaining();

    if (remaining === null) {
        return null;
    }

    const label = `${remaining} of ${dailyPasteis} pastéis left today, resets at 00:00 UTC`;

    return (
        <span
            title={label}
            className={cn(
                'inline-flex items-center gap-1 font-mono text-xs text-muted-foreground tabular-nums',
                className,
            )}
            data-test="pasteis-balance"
        >
            <img
                src="/pastel-de-nata.svg"
                alt=""
                width={16}
                height={16}
                className="size-4 shrink-0 object-contain"
            />
            {isCompact ? (
                <>
                    {remaining}
                    <span className="sr-only"> pastéis left today</span>
                </>
            ) : (
                <span>
                    {remaining}/{dailyPasteis} left today
                </span>
            )}
        </span>
    );
}
