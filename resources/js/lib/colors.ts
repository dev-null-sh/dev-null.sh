// A hex color like "#ff8a4c" or "#fff", which the web shows next to a swatch of it. It has 3, 4, 6 or 8 hex digits. One
// of 3 or 4 digits also needs a letter or a leading zero, so "#123" stays an issue reference while "#000" is black: issue
// numbers never start with a zero, and none are 6 or 8 digits long. It is a whole word like a hashtag: it starts at the start,
// after whitespace or after the ">" that starts a quoted line, and only punctuation may follow it before the next
// space, so neither "#fff-x" nor "#ff8a4c.com" is a color.
const colorPattern =
    // oxlint-disable-next-line no-misleading-character-class
    /(?:(?<!\S)|(?<=\n>))[#\uFF03]([0-9a-f]{8}|[0-9a-f]{6}|(?=[0-9]*[a-f]|0)[0-9a-f]{3,4})(?=\p{P}*(?:[\s\u00AD\u2060\u200A-\u200D\u20E2]|$))/giu;

export type Color = {
    value: string;
    index: number;
    length: number;
};

/**
 * The hex colors in a run of prose, where they are and how long they are with the "#". When the prose starts a whole
 * body, its start is the start of a line, so a ">" there starts a quoted line.
 */
export function colorsIn(text: string, startsText = false): Color[] {
    const start = startsText ? 1 : 0;

    return [...(startsText ? `\n${text}` : text).matchAll(colorPattern)].map(
        (match) => ({
            value: match[1],
            index: match.index - start,
            length: match[0].length,
        }),
    );
}
