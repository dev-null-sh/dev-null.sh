import { cn } from '@/lib/utils';

/**
 * A pulsing bar standing in for text that is still loading, inline so its line keeps the height the text will have.
 * It fades in late, so a quick answer never shows it.
 */
export function Placeholder({ className }: { className?: string }) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-block h-[0.85em] animate-in align-middle delay-300 duration-300 fill-mode-both fade-in',
                className,
            )}
        >
            <span className="block size-full animate-pulse rounded-sm bg-primary/10" />
        </span>
    );
}
