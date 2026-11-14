// A word pastel, pasteis or pastéis, or one escaped with a backslash right before it to keep it a word. A backslash
// that follows a word character escapes nothing, and the word after it is left alone too.
const pastelPattern =
    /(?<![\p{L}\p{M}\p{N}_\\])(\\?)(pastel|pasteis|pastéis)(?![\p{L}\p{M}\p{N}_])/giu;

export type PasteisPart = { text: string; pastel: boolean };

/**
 * Splits the text into the words drawn as a pastel and the text between them, which keeps an escaped word without
 * its backslash.
 */
export function pasteisParts(text: string): PasteisPart[] {
    const parts: PasteisPart[] = [];
    let prose = '';
    let position = 0;

    for (const match of text.matchAll(pastelPattern)) {
        prose += text.slice(position, match.index);
        position = match.index + match[0].length;

        if (match[1] !== '') {
            prose += match[2];

            continue;
        }

        parts.push(
            { text: prose, pastel: false },
            { text: match[2], pastel: true },
        );
        prose = '';
    }

    parts.push({ text: prose + text.slice(position), pastel: false });

    return parts.filter((part) => part.pastel || part.text !== '');
}
