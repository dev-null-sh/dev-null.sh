const NOT_A_LETTER = /[^BCDFGHJKLMNPQRSTVWXZ]/g;

const LENGTH = 8;

/**
 * The code as the terminal shows it, out of what the person typed: its letters in uppercase, anything else left out,
 * at most eight of them, with the dash after the fourth as soon as there is a fifth.
 */
export function formatUserCode(input: string): string {
    const letters = input
        .toUpperCase()
        .replace(NOT_A_LETTER, '')
        .slice(0, LENGTH);

    return letters.length > 4
        ? `${letters.slice(0, 4)}-${letters.slice(4)}`
        : letters;
}
