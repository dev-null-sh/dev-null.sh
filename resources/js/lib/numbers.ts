const separatedFormatter = new Intl.NumberFormat('en-US');

const shortenFrom = 10_000;

const compactFormatter = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
    roundingMode: 'trunc',
});

/**
 * Counts with thousands separators from a thousand (1,250), shortened from ten thousand (10.2k). Shortened counts are
 * cut, not rounded, so 999,999 never shows as 1m.
 */
export function formatCount(count: number): string {
    return count >= shortenFrom
        ? compactFormatter.format(count).toLowerCase()
        : separatedFormatter.format(count);
}

/**
 * The exact count with thousands separators (10,250), for labels where a shortened count would lose detail, like
 * screen reader labels next to a shortened count.
 */
export function formatExactCount(count: number): string {
    return separatedFormatter.format(count);
}

/**
 * The exact count to show on hover, only when formatCount shortened it.
 */
export function exactCountTitle(count: number): string | undefined {
    return count >= shortenFrom ? formatExactCount(count) : undefined;
}
