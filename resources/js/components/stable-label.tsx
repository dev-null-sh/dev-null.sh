import { cn } from '@/lib/utils';

export function StableLabel({
    label,
    pendingLabel,
    isPending,
}: {
    label: string;
    pendingLabel: string;
    isPending: boolean;
}) {
    return (
        <span className="grid text-center">
            <span
                className={cn('col-start-1 row-start-1', {
                    invisible: isPending,
                })}
            >
                {label}
            </span>
            <span
                className={cn('col-start-1 row-start-1', {
                    invisible: !isPending,
                })}
            >
                {pendingLabel}
            </span>
        </span>
    );
}
