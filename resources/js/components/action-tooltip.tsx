import type { ReactNode } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

/**
 * Wraps the trigger in a span, because disabled buttons fire no pointer events and would never show why they are
 * disabled.
 */
export function ActionTooltip({
    label,
    shortcut,
    children,
}: {
    label: ReactNode;
    shortcut?: string;
    children: ReactNode;
}) {
    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <span className="inline-flex shrink-0">{children}</span>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
                {label}
                {shortcut && (
                    <kbd className="rounded border border-b-2 bg-muted px-1.5 font-mono text-[11px] text-muted-foreground">
                        {shortcut}
                    </kbd>
                )}
            </TooltipContent>
        </Tooltip>
    );
}
