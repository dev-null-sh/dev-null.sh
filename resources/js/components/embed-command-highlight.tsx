import { findEmbedCommand } from '@/lib/embed';
import { cn } from '@/lib/utils';

/**
 * Sits behind a textarea with the same text, invisible, so that only the highlight around a working /embed command
 * shows through. Give it the textarea's padding and type styles, and the textarea `relative` so it stays on top.
 */
export function EmbedCommandHighlight({
    text,
    className,
}: {
    text: string;
    className?: string;
}) {
    const command = findEmbedCommand(text);

    if (!command) {
        return null;
    }

    return (
        <div
            aria-hidden
            className={cn(
                'pointer-events-none absolute inset-0 overflow-hidden [overflow-wrap:break-word] whitespace-pre-wrap text-transparent',
                className,
            )}
            data-test="embed-command-highlight"
        >
            {text.slice(0, command.start)}
            <mark className="rounded-sm bg-primary/15 text-transparent ring-2 ring-primary/15">
                {command.text}
            </mark>
        </div>
    );
}
