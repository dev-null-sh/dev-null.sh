import { usePage } from '@inertiajs/react';

/**
 * Whether this device shows messages of only a few emoji large: on unless turned off in Settings → Appearance.
 *
 * In the browser the cookie is read directly: a page restored by Back keeps the props it was first shown with, which
 * may predate turning the setting off. The server reads the same cookie, so the first render matches.
 */
export function useLargeEmoji(): boolean {
    const { showsLargeEmoji } = usePage().props;
    const saved =
        typeof document === 'undefined'
            ? undefined
            : /(?:^|; )large_emoji=([^;]*)/.exec(document.cookie)?.[1];

    return saved === undefined ? showsLargeEmoji !== false : saved !== 'false';
}
