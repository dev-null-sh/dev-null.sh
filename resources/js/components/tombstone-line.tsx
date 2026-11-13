import { cn } from '@/lib/utils';

/**
 * What a commit that can't be shown reads, in its place: a mono label, then a sentence. The label never wraps; the
 * sentence goes under it when the line runs out of room.
 */
export function TombstoneLine({
    label = '[deleted]',
    sentence = 'This commit was deleted.',
    size = 'commit',
    className,
}: {
    label?: string;
    sentence?: string;
    size?: 'commit' | 'reply';
    className?: string;
}) {
    return (
        <p
            className={cn(
                'flex flex-wrap gap-x-1.5 text-muted-foreground',
                size === 'reply' ? 'text-sm' : 'text-[15px]',
                className,
            )}
            data-test="tombstone-line"
        >
            <span
                className={cn(
                    'shrink-0 font-mono whitespace-nowrap',
                    size === 'reply' ? 'text-xs' : 'text-[13px]',
                )}
            >
                {label}
            </span>
            {sentence}
        </p>
    );
}
