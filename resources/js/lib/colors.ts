const hexDigit = '[0-9a-f]';
const longHex = `${hexDigit}{8}|${hexDigit}{6}`;
const shortHex = `${hexDigit}{3,4}`;
const hexColor = `${longHex}|${shortHex}`;

// Keep explicit colors in sync with app/Services/Hashtags.php.
const explicitColorStart = String.raw`(?<![\p{L}\p{M}\p{N}_#])color\(`;
const explicitColorPattern = String.raw`${explicitColorStart}#(${hexColor})\)`;
const typingExplicitColorPattern = new RegExp(
    `${explicitColorStart}(?:#${hexDigit}*)?$`,
    'iu',
);
const hexColorPattern = new RegExp(`^#(?:${hexColor})$`, 'i');

// A bare ASCII "#" followed by a letter marks a hashtag. The lookahead on short hex keeps "#123" an issue reference.
// Bare colors start at whitespace or a quote boundary and may end in punctuation before the next space.
const bareColorPattern = String.raw`(?:(?<!\S)|(?<=\n>))(?:#(?=[0-9])|\uFF03)(${longHex}|(?=[0-9]*[a-f]|0)${shortHex})(?=\p{P}*(?:[\s\u00AD\u2060\u200A-\u200D\u20E2]|$))`;

// A channel of a color function, like "255", "0.75", "65%" or "50deg".
const channel = String.raw`[+-]?(?:\d+(?:\.\d+)?|\.\d+)(?:%|deg)?`;

// A CSS color function like "rgb(255 138 76)", "hsl(20 100% 65%)" or "oklch(0.75 0.15 50 / 50%)", a whole word like a bare
// color. Its three channels are on one line, separated by spaces with an optional "/ alpha" or, in rgb() and hsl() only, by
// commas with an optional fourth, so "rgb(1, 2 3)", "lab(1, 2, 3)" and "hsl(1 2 3 4)" stay text.
const colorFunctionPattern = String.raw`(?:(?<!\S)|(?<=\n>))(?:(?:rgba?|hsla?|(?:ok)?lab|(?:ok)?lch)\([ \t]*${channel}[ \t]+${channel}[ \t]+${channel}(?:[ \t]*/[ \t]*${channel})?|(?:rgba?|hsla?)\([ \t]*${channel}[ \t]*,[ \t]*${channel}[ \t]*,[ \t]*${channel}(?:[ \t]*,[ \t]*${channel})?)[ \t]*\)(?=\p{P}*(?:[\s\u00AD\u2060\u200A-\u200D\u20E2]|$))`;
const colorPattern = new RegExp(
    `${explicitColorPattern}|${bareColorPattern}|${colorFunctionPattern}`,
    'giu',
);

export function isHexColor(text: string): boolean {
    return hexColorPattern.test(text);
}

export function isTypingExplicitColor(text: string): boolean {
    return typingExplicitColorPattern.test(text);
}

/**
 * A hex color's digits, without the "#", or a whole color function, as written.
 */
export type Color = {
    value: string;
    label: string;
    index: number;
    length: number;
};

/**
 * The hex colors and color functions in a run of prose, with their display labels and source ranges. When the prose
 * starts a whole body, its start is the start of a line, so a ">" there starts a quoted line.
 */
export function colorsIn(text: string, startsText = false): Color[] {
    const start = startsText ? 1 : 0;

    return [...(startsText ? `\n${text}` : text).matchAll(colorPattern)].map(
        (match) => ({
            value: match[1] ?? match[2] ?? match[0],
            label: match[1] === undefined ? match[0] : `#${match[1]}`,
            index: match.index - start,
            length: match[0].length,
        }),
    );
}

/**
 * What a color's swatch is painted with: a hex color's digits with their "#", or a color function as written.
 */
export function cssColor(color: Color): string {
    return color.value.endsWith(')') ? color.value : `#${color.value}`;
}
