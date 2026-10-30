import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';

/** A quiet, non-interactive record of the client that created a commit. */
export function CommitSourceBadge({
    source,
    size = 'default',
}: {
    source?: string | null;
    size?: 'default' | 'small';
}) {
    if (source === null || source === undefined) {
        return null;
    }

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span
                    className={cn(
                        'shrink-0 self-center rounded-sm bg-muted px-1.5 font-mono text-muted-foreground',
                        size === 'default' ? 'py-0.5 text-xs' : 'text-[11px]',
                    )}
                    aria-label={`Committed via ${source}`}
                    data-test="commit-source"
                >
                    {source}
                </span>
            </TooltipTrigger>
            <TooltipContent>Committed via {source}</TooltipContent>
        </Tooltip>
    );
}
