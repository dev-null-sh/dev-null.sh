const graphemes = new Intl.Segmenter(undefined, { granularity: 'grapheme' });

const whitespace = /^\s+$/u;
const pictographic = /^\p{Extended_Pictographic}/u;
const drawnAsEmoji = /\p{Emoji_Presentation}|️|\p{Emoji_Modifier}|‍/u;
const flag = /^\p{Regional_Indicator}{2}$/u;
const keycap = /^[#*0-9]️?⃣$/u;

/**
 * Whether one character as people see it is an emoji. Symbols that browsers draw as text unless asked otherwise,
 * like © or ™, don't count.
 */
function isEmoji(grapheme: string): boolean {
    return (
        (pictographic.test(grapheme) && drawnAsEmoji.test(grapheme)) ||
        flag.test(grapheme) ||
        keycap.test(grapheme)
    );
}

/**
 * Whether a text is nothing but emoji, with any spaces or line breaks between them.
 */
export function isEmojiOnly(text: string): boolean {
    let hasEmoji = false;

    for (const { segment } of graphemes.segment(text)) {
        if (whitespace.test(segment)) {
            continue;
        }

        if (!isEmoji(segment)) {
            return false;
        }

        hasEmoji = true;
    }

    return hasEmoji;
}
