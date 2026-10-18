const compactFormatter = new Intl.NumberFormat('en-US', {
    notation: 'compact',
    maximumFractionDigits: 1,
});

export function formatCount(count: number): string {
    return count > 1000
        ? compactFormatter.format(count).toLowerCase()
        : String(count);
}
