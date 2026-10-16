import { mapTypedProse, trailingTypedProse } from '@/lib/code';
import emojiShortcodes from '@/lib/emoji-shortcodes.json?raw';

const shortcodeEmoji = JSON.parse(emojiShortcodes) as Record<string, string>;

const escapeRegExp = (name: string): string =>
    name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const shortcodeNames = Object.keys(shortcodeEmoji).sort(
    (left, right) => right.length - left.length,
);

const shortcodePattern = new RegExp(
    `:(${shortcodeNames.map(escapeRegExp).join('|')}):?(?![A-Za-z0-9_])`,
    'g',
);

// The shortcode still being typed right before the caret, like ":eye" on the way to ":eyes".
const typingPattern = /:[A-Za-z0-9_+-]+$/;

// What the emoji suggestions open for: ":" at the start of a word and at least two characters, like Slack, so
// "10:30", "std::vec" and emoticons like ":D" stay quiet.
const queryPattern = /(?<![A-Za-z0-9_:]):([A-Za-z0-9_+-]{2,})$/;

const suggestionLimit = 8;

export function emojify(text: string): string {
    return text.replace(
        shortcodePattern,
        (_, name: string) => shortcodeEmoji[name],
    );
}

/**
 * Turns the typed shortcodes into emoji, except the one at the caret: ":eye" may still become ":eyes", so it waits
 * for a closing ":", a space, or a pick from the suggestions.
 */
export function emojifyInput(
    text: string,
    caret: number,
): { text: string; caret: number } {
    const typingAt = typingPattern.exec(text.slice(0, caret))?.index ?? -1;

    const emojifyTyped = (typed: string) =>
        mapTypedProse(typed, (prose, offset) =>
            prose.replace(
                shortcodePattern,
                (match: string, name: string, index: number) => {
                    const start = offset + index;

                    if (start === typingAt) {
                        return match;
                    }

                    // ":heart:eyes" keeps the second colon for the shortcode being typed.
                    if (start + match.length - 1 === typingAt) {
                        return `${shortcodeEmoji[name]}:`;
                    }

                    return shortcodeEmoji[name];
                },
            ),
        );

    return {
        text: emojifyTyped(text),
        caret: emojifyTyped(text.slice(0, caret)).length,
    };
}

export type EmojiSuggestion = {
    name: string;
    emoji: string;
};

export type EmojiQuery = {
    start: number;
    query: string;
};

/**
 * The ":shortcode" being typed at the end of the text, outside code.
 */
export function emojiQueryBefore(text: string): EmojiQuery | null {
    const match = queryPattern.exec(trailingTypedProse(text));

    if (match === null) {
        return null;
    }

    return { start: text.length - match[0].length, query: match[1] };
}

/**
 * Names starting with the query first, then names with a word starting with it (":eyes" also finds "roll_eyes"),
 * shortest first.
 */
export function suggestEmojis(query: string): EmojiSuggestion[] {
    const partial = query.toLowerCase();

    const rank = (name: string): number => {
        if (name.startsWith(partial)) {
            return 0;
        }

        return name.includes(`_${partial}`) ? 1 : -1;
    };

    return Object.entries(shortcodeEmoji)
        .map(([name, emoji]) => ({ name, emoji, rank: rank(name) }))
        .filter((suggestion) => suggestion.rank !== -1)
        .sort(
            (left, right) =>
                left.rank - right.rank ||
                left.name.length - right.name.length ||
                left.name.localeCompare(right.name),
        )
        .slice(0, suggestionLimit)
        .map(({ name, emoji }) => ({ name, emoji }));
}
