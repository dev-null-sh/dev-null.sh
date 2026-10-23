import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type SearchFailure = 'failed' | 'throttled';

export function SearchError({
    reason,
    onRetry,
    className,
}: {
    reason: SearchFailure;
    onRetry: () => void;
    className?: string;
}) {
    return (
        <div
            role="alert"
            className={cn('flex flex-wrap items-center gap-3', className)}
        >
            <p className="font-mono text-[13px] text-muted-foreground">
                {reason === 'throttled'
                    ? 'error: too many searches, give it a few seconds'
                    : 'fatal: could not complete the search, please try again'}
            </p>
            <Button variant="outline" size="sm" onClick={onRetry}>
                Try again
            </Button>
        </div>
    );
}
