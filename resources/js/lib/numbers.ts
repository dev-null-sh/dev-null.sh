const compact = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const full = new Intl.NumberFormat('en-US');

/**
 * Keeps big counts short enough for a stats row: 999, 1.2K, 3.4M, 1B.
 */
export function formatCompactCount(count: number): string {
    return compact.format(count);
}

/**
 * The exact count, for a title next to a compact one: 1,234,567.
 */
export function formatFullCount(count: number): string {
    return full.format(count);
}
