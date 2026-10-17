import { mapTypedProse } from '@/lib/code';
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
 * The shortcodes that start with the query, shortest first, then the ones that only contain it.
 */
export function searchEmoji(query: string, limit = 8): EmojiSuggestion[] {
    const partial = query.toLowerCase();
    const byLength = (left: string, right: string): number =>
        left.length - right.length || left.localeCompare(right);
    const names = Object.keys(shortcodeEmoji);

    return [
        ...names.filter((name) => name.startsWith(partial)).sort(byLength),
        ...names
            .filter(
                (name) => !name.startsWith(partial) && name.includes(partial),
            )
            .sort(byLength),
    ]
        .slice(0, limit)
        .map((name) => ({ name, emoji: shortcodeEmoji[name] }));
}
