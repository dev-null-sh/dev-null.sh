// A hex color like "#ff8a4c" or "#fff", which the web shows next to a swatch of it. It has 3, 4, 6 or 8 hex digits. One
// of 3 or 4 digits also needs a letter or a leading zero, so "#123" stays an issue reference while "#000" is black: issue
// numbers never start with a zero, and none are 6 or 8 digits long. It is a whole word like a hashtag: it starts at the start,
// after whitespace or after the ">" that starts a quoted line, and only punctuation may follow it before the next
// space, so neither "#fff-x" nor "#ff8a4c.com" is a color.
const colorPattern =
    // oxlint-disable-next-line no-misleading-character-class
    /(?:(?<!\S)|(?<=\n>))[#\uFF03]([0-9a-f]{8}|[0-9a-f]{6}|(?=[0-9]*[a-f]|0)[0-9a-f]{3,4})(?=\p{P}*(?:[\s\u00AD\u2060\u200A-\u200D\u20E2]|$))/giu;

// A channel of a color function, like "255", "0.75", "65%" or "50deg".
const channel = String.raw`[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:%|deg)?`;

// A CSS color function like "rgb(255 138 76)", "hsl(20 100% 65%)" or "oklch(0.75 0.15 50 / 50%)", a whole word like a hex
// color. Its three channels are on one line, separated by spaces with an optional "/ alpha" or by commas with an optional
// fourth, so "rgb(1, 2 3)" and "hsl(1 2 3 4)" stay text.
const colorFunctionPattern = new RegExp(
    String.raw`(?:(?<!\S)|(?<=\n>))(?:rgba?|hsla?|(?:ok)?lab|(?:ok)?lch)\([ \t]*(?:${channel}[ \t]+${channel}[ \t]+${channel}(?:[ \t]*/[ \t]*${channel})?|${channel}[ \t]*,[ \t]*${channel}[ \t]*,[ \t]*${channel}(?:[ \t]*,[ \t]*${channel})?)[ \t]*\)(?=\p{P}*(?:[\s\u00AD\u2060\u200A-\u200D\u20E2]|$))`,
    'giu',
);

/**
 * A hex color's digits, without the "#", or a whole color function, as written.
 */
export type Color = {
    value: string;
    index: number;
    length: number;
};

/**
 * The hex colors and color functions in a run of prose, in order, where they are and how long they are as written. When
 * the prose starts a whole body, its start is the start of a line, so a ">" there starts a quoted line.
 */
export function colorsIn(text: string, startsText = false): Color[] {
    const start = startsText ? 1 : 0;
    const prose = startsText ? `\n${text}` : text;

    return [
        ...[...prose.matchAll(colorPattern)].map((match) => ({
            value: match[1],
            index: match.index - start,
            length: match[0].length,
        })),
        ...[...prose.matchAll(colorFunctionPattern)].map((match) => ({
            value: match[0],
            index: match.index - start,
            length: match[0].length,
        })),
    ].sort((a, b) => a.index - b.index);
}

/**
 * What a color's swatch is painted with: a hex color's digits with their "#", or a color function as written.
 */
export function cssColor(color: Color): string {
    return color.value.endsWith(')') ? color.value : `#${color.value}`;
}
