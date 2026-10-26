import { ArrowDown, ArrowUp } from 'lucide-react';
import { formatCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';

/**
 * How a period compares with the one before, as a signed percentage behind an arrow. It is green when it moved the
 * good way for its metric and red when it moved the bad way; a period after an empty one prints the count it gained,
 * and a rise past tenfold prints the multiple.
 */
export function Delta({
    current,
    previous,
    isUpGood = true,
    label,
}: {
    current: number;
    previous: number;
    isUpGood?: boolean;
    label: string;
}) {
    const change =
        previous === 0
            ? current
            : Math.round(((current - previous) / previous) * 100);
    const isUp = change > 0;

    return (
        <span className="inline-flex min-w-0 items-center gap-1.5 font-mono text-[13px] whitespace-nowrap tabular-nums">
            {change === 0 ? (
                <span className="text-muted-foreground">±0%</span>
            ) : (
                <span
                    className={cn(
                        'inline-flex items-center gap-0.5 font-medium',
                        isUp === isUpGood ? 'text-success' : 'text-destructive',
                    )}
                >
                    {isUp ? (
                        <ArrowUp aria-hidden className="size-3.5" />
                    ) : (
                        <ArrowDown aria-hidden className="size-3.5" />
                    )}
                    {previous > 0 && change >= 1000
                        ? `×${formatCount(Math.round(current / previous))}`
                        : `${isUp ? '+' : '−'}${formatCount(Math.abs(change))}${previous === 0 ? '' : '%'}`}
                </span>
            )}
            <span className="truncate text-muted-foreground">{label}</span>
        </span>
    );
}
