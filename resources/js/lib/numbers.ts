const compactFormatter = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const truncatingFormatter = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
    roundingMode: 'trunc',
});

const fullFormatter = new Intl.NumberFormat('en-US');

export function formatCount(count: number): string {
    return count > 1000
        ? compactFormatter.format(count).toLowerCase()
        : String(count);
}

/**
 * Keeps big counts short enough for a stats row: 999, 1.2K, 3.4M, 1B.
 * It truncates instead of rounding, so a count never reads higher than it is: 999,999 is 999.9K, not 1M.
 */
export function formatCompactCount(count: number): string {
    return truncatingFormatter.format(count);
}

/**
 * The exact count, for a title next to a compact one: 1,234,567.
 */
export function formatFullCount(count: number): string {
    return fullFormatter.format(count);
}
