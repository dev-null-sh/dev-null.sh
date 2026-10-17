import { useLang } from '@erag/lang-sync-inertia/react';
import { FoldVertical } from 'lucide-react';
import {
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
    className,
}: {
    id?: string;
    body: string;
    className?: string;
}) {
    const { __, transChoice } = useLang();
    const remaining = maxBodyLength - characterCount(body);
    const isCounting = remaining <= countFrom;
    const isFolded = !isCounting && isFoldedInLists(body);
    const foldHint = __('composer.counter.fold_hint', {
        length: previewLength,
        lines: previewLines,
    });

    return (
        <span
            id={id}
            aria-label={
                isCounting
                    ? transChoice('composer.counter.remaining', remaining)
                    : isFolded
                      ? foldHint
                      : undefined
            }
            title={isFolded ? foldHint : undefined}
            className={cn(
                'inline-flex w-[3ch] shrink-0 justify-end font-mono text-muted-foreground tabular-nums',
                {
                    'text-amber-700 dark:text-amber-400':
                        isCounting && remaining <= 20,
                    'text-destructive': isCounting && remaining === 0,
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
