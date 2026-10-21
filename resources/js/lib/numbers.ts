const compactFormatter = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

const fullFormatter = new Intl.NumberFormat('en-US');

export function formatCount(count: number): string {
    return count >= 1000
        ? compactFormatter.format(count).toLowerCase()
        : String(count);
}

/**
 * The full number with thousands separators, 1,000 instead of 1000.
 */
export function formatNumber(count: number): string {
    return fullFormatter.format(count);
}
