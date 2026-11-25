import { CircleAlert } from 'lucide-react';
import { useRef, useState } from 'react';
import { readPixels, visibility } from '@/lib/image-contrast';
import type { Visibility } from '@/lib/image-contrast';
import { cn } from '@/lib/utils';

const modes = [
    { theme: 'light', label: 'Light mode' },
    { theme: 'dark', label: 'Dark mode' },
] as const;

const statuses: Record<Visibility, string> = {
    easy: 'Easy to see',
    partly: 'Partly hard to see',
    wcag: 'Hard to see (WCAG)',
    hard: 'Hard to see',
};

/**
 * Shows an image on the light and dark mode backgrounds and flags a mode where all or part of it is
 * hard to see.
 * `label` names the image for screen readers; `size` is the size, in pixels, it is shown at elsewhere,
 * so the check sees it as people will. The image is shown and checked as a centered square, and only
 * a same-origin (or CORS-enabled) `src` can be checked: another shows without a status.
 */
export function ImageContrastPreview({
    src,
    label = 'Image',
    size,
}: {
    src: string;
    label?: string;
    size: number;
}) {
    return (
        <div
            className="@container grid grid-cols-2 gap-2"
            data-test="image-contrast-preview"
            role="group"
            aria-label={`${label} preview in light and dark mode`}
        >
            {modes.map((mode) => (
                // Keyed by the image, so a new one starts without a status.
                <PreviewTile
                    key={`${mode.theme}-${src}`}
                    src={src}
                    size={size}
                    {...mode}
                />
            ))}
        </div>
    );
}

// data-theme-scope gives the tile its mode's regular tokens. Only var(--…) reads follow it:
// bg-muted-style utilities resolve at the root.
function PreviewTile({
    src,
    size,
    theme,
    label,
}: {
    src: string;
    size: number;
    theme: 'light' | 'dark';
    label: string;
}) {
    const tile = useRef<HTMLDivElement>(null);
    const [status, setStatus] = useState<Visibility | null>(null);
    const warns = status !== null && status !== 'easy';

    return (
        <div
            ref={tile}
            data-theme-scope={theme}
            aria-live="polite"
            aria-atomic
            // Stacks when narrow. A warning's border takes the page's red, which stands out from the page,
            // and its inset ring the tile's, which stands out from the tile, without moving anything.
            className={cn(
                'flex flex-col items-start gap-2 rounded-md border border-(--border) bg-(--muted) p-3 text-(--foreground) @sm:flex-row @sm:items-center @sm:gap-3',
                warns &&
                    'border-destructive shadow-[inset_0_0_0_1px_var(--destructive)]',
            )}
        >
            <span className="inline-flex size-10 shrink-0 overflow-hidden rounded-sm">
                <img
                    src={src}
                    alt=""
                    className="size-full object-cover"
                    onLoad={(event) => {
                        let pixels;
                        try {
                            pixels = readPixels(event.currentTarget, size);
                        } catch {
                            // Pixels it can't read (another origin's, say) leave no status.
                            return;
                        }
                        setStatus(
                            visibility(
                                pixels,
                                getComputedStyle(tile.current!)
                                    .getPropertyValue('--muted')
                                    .trim(),
                            ),
                        );
                    }}
                />
            </span>
            <span className="flex min-w-0 flex-col gap-0.5 text-xs">
                <span>{label}</span>
                <span
                    className={cn(
                        'flex min-h-4 items-start gap-1',
                        warns
                            ? 'text-(--destructive)'
                            : 'text-(--muted-foreground)',
                    )}
                >
                    {warns && (
                        <CircleAlert
                            aria-hidden
                            className="size-3.5 shrink-0 translate-y-px"
                        />
                    )}
                    {/* Wraps beside the icon when the tile is narrow. */}
                    {status && (
                        <span className="text-balance">{statuses[status]}</span>
                    )}
                </span>
            </span>
        </div>
    );
}
