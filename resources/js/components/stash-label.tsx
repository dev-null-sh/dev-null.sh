import { useLang } from '@erag/lang-sync-inertia/react';
import { cn } from '@/lib/utils';

export function StashLabel({
    onDrop,
    className,
}: {
    onDrop: () => void;
    className?: string;
}) {
    const { __ } = useLang();

    return (
        <span
            className={cn(
                'flex items-center gap-1 font-mono text-muted-foreground',
                className,
            )}
            data-test="stash-label"
        >
            <span title={__('composer.stash.restored')}>{'stash@{0}'}</span>
            <button
                type="button"
                onClick={onDrop}
                title={__('composer.stash.drop_title')}
                className="rounded px-1 underline decoration-rail underline-offset-[3px] transition-colors hover:bg-accent hover:text-foreground hover:decoration-current focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                data-test="drop-stash-button"
            >
                drop
            </button>
        </span>
    );
}
