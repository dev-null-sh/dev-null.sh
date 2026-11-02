import { FoldVertical } from 'lucide-react';
import {
    bodyCharacterCount,
    isFoldedInLists,
    maxBodyLength,
    previewLength,
    previewLines,
} from '@/lib/commit';
import { characterCount, cn } from '@/lib/utils';

const countFrom = 200;

/**
 * The characters left, once few are. Before that, a hint when the body is long enough to be folded in lists. Both
 * share one fixed slot, so the buttons next to it never move while typing.
 */
export function BodyCounter({
    id,
    body,
    isIssue = false,
    className,
}: {
    id?: string;
    body: string;
    isIssue?: boolean;
    className?: string;
}) {
    const remaining =
        maxBodyLength -
        (isIssue ? characterCount(body) : bodyCharacterCount(body));
    const isCounting = remaining <= countFrom;
    const isFolded = !isCounting && isFoldedInLists(body);
    const foldHint = `Feeds show the first ${previewLength} characters or ${previewLines} lines, then “Show more”.`;

    return (
        <span
            id={id}
            aria-label={
                isCounting
                    ? `${remaining} characters left`
                    : isFolded
                      ? foldHint
                      : undefined
            }
            title={isFolded ? foldHint : undefined}
            className={cn(
                'inline-flex w-[6ch] shrink-0 justify-end font-mono text-muted-foreground tabular-nums',
                {
                    'text-amber-700 dark:text-amber-400':
                        isCounting && remaining > 0 && remaining <= 20,
                    'text-destructive': isCounting && remaining <= 0,
                },
                className,
            )}
            data-test="body-counter"
        >
            {isCounting && remaining}
            {isFolded && <FoldVertical aria-hidden className="size-4" />}
        </span>
    );
}
