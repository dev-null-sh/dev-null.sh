import { mapTypedProse } from '@/lib/code';
import emojiShortcodes from '@/lib/emoji-shortcodes.json?raw';

const shortcodeEmoji = JSON.parse(emojiShortcodes) as Record<string, string>;

const escapeRegExp = (name: string): string =>
    name.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');

const shortcodeNames = Object.keys(shortcodeEmoji).sort(
    (left, right) => right.length - left.length,
);

const shortcodePattern = new RegExp(
    `:(${shortcodeNames.map(escapeRegExp).join('|')}):`,
    'g',
);

export function emojify(text: string): string {
    return text.replace(
        shortcodePattern,
        (_, name: string) => shortcodeEmoji[name],
    );
}

export function emojifyInput(
    text: string,
    caret: number,
): { text: string; caret: number } {
    const emojifyTyped = (typed: string) => mapTypedProse(typed, emojify);

    return {
        text: emojifyTyped(text),
        caret: emojifyTyped(text.slice(0, caret)).length,
    };
}

export type EmojiSuggestion = {
    name: string;
    emoji: string;
};

/**
 * The shortcodes that start with the query, then the ones with a later word starting with it, then the ones that only
 * contain it, each shortest first.
 */
export function searchEmoji(query: string, limit = 8): EmojiSuggestion[] {
    const partial = query.toLowerCase();
    const rank = (name: string): number => {
        if (name.startsWith(partial)) {
            return 0;
        }

        return name.split(/[_-]/).some((word) => word.startsWith(partial))
            ? 1
            : 2;
    };

    return Object.keys(shortcodeEmoji)
        .filter((name) => name.includes(partial))
        .map((name) => ({ name, rank: rank(name) }))
        .sort(
            (left, right) =>
                left.rank - right.rank ||
                left.name.length - right.name.length ||
                left.name.localeCompare(right.name),
        )
        .slice(0, limit)
        .map(({ name }) => ({ name, emoji: shortcodeEmoji[name] }));
}
