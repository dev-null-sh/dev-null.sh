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

const emojiNames = new Map<string, string>();

Object.entries(shortcodeEmoji).forEach(([name, emoji]) => {
    if (!emojiNames.has(emoji)) {
        emojiNames.set(emoji, name);
    }
});

/**
 * The first shortcode for an emoji, alphabetically.
 */
export function emojiName(emoji: string): string | undefined {
    return emojiNames.get(emoji);
}

/**
 * 0 when the shortcode starts with the query, 1 when a later word does, 2 when it only contains it.
 */
function matchRank(name: string, partial: string): number {
    if (name.startsWith(partial)) {
        return 0;
    }

    return name.split(/[_-]/).some((word) => word.startsWith(partial)) ? 1 : 2;
}

/**
 * The shortcodes that start with the query, then the ones with a later word starting with it, then the ones that only
 * contain it, each shortest first.
 */
export function searchEmoji(query: string, limit = 8): EmojiSuggestion[] {
    const partial = query.toLowerCase();

    return Object.keys(shortcodeEmoji)
        .filter((name) => name.includes(partial))
        .map((name) => ({ name, rank: matchRank(name, partial) }))
        .sort(
            (left, right) =>
                left.rank - right.rank ||
                left.name.length - right.name.length ||
                left.name.localeCompare(right.name),
        )
        .slice(0, limit)
        .map(({ name }) => ({ name, emoji: shortcodeEmoji[name] }));
}

/**
 * Each emoji once, under its best matching shortcode, ranked as searchEmoji does but with the emoji the user uses
 * most first within each kind of match. Colons around the query are ignored.
 */
export function searchEmojiByUse(
    query: string,
    uses: ReadonlyMap<string, number>,
    limit = 80,
): EmojiSuggestion[] {
    const partial = query
        .trim()
        .toLowerCase()
        .replace(/^:+|:+$/g, '');
    const best = new Map<string, { name: string; rank: number }>();

    Object.keys(shortcodeEmoji)
        .filter((name) => name.includes(partial))
        .forEach((name) => {
            const emoji = shortcodeEmoji[name];
            const rank = matchRank(name, partial);
            const current = best.get(emoji);

            if (
                current === undefined ||
                rank < current.rank ||
                (rank === current.rank && name.length < current.name.length)
            ) {
                best.set(emoji, { name, rank });
            }
        });

    return [...best.entries()]
        .sort(
            ([leftEmoji, left], [rightEmoji, right]) =>
                left.rank - right.rank ||
                (uses.get(rightEmoji) ?? 0) - (uses.get(leftEmoji) ?? 0) ||
                left.name.length - right.name.length ||
                left.name.localeCompare(right.name),
        )
        .slice(0, limit)
        .map(([emoji, { name }]) => ({ name, emoji }));
}
