import { mapTypedProse } from '@/lib/code';
import emojiShortcodes from '@/lib/emoji-shortcodes.json?raw';
import type { BodySegment } from '@/types';

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
    if (!text.includes(':')) {
        return text;
    }

    return text.replace(
        shortcodePattern,
        (_, name: string) => shortcodeEmoji[name],
    );
}

const maxJumboEmoji = 3;
// Longer than any body of only emoji worth telling apart, so long prose is never split into graphemes.
const maxEmojiOnlyLength = 128;
// Letters outside a shortcode mean words, whatever else the body holds.
const shortcodeLike = /:[a-z0-9_+-]+:/gi;

let emojiSupport:
    | { graphemes: Intl.Segmenter; singleEmoji: RegExp }
    | null
    | undefined;

/**
 * Built on first use, never on load: Safari before 17 lacks the `v` flag and Firefox before 125 lacks Intl.Segmenter,
 * and either throwing while this module loads would take every page down with it. Without them, emoji stay small.
 */
function supportedEmoji(): typeof emojiSupport {
    if (emojiSupport === undefined) {
        try {
            emojiSupport = {
                graphemes: new Intl.Segmenter('en', {
                    granularity: 'grapheme',
                }),
                // One whole emoji: a flag, a skin tone, a keycap or a ZWJ family each make one. Bare `1`, `#` or `©` don't.
                singleEmoji: new RegExp('^\\p{RGI_Emoji}$', 'v'),
            };
        } catch {
            emojiSupport = null;
        }
    }

    return emojiSupport;
}

/**
 * How many emoji a body is made of when it is nothing but emoji, with shortcodes counted as theirs; 0 otherwise.
 */
export function emojiOnlyCount(segments: BodySegment[]): number {
    const [segment] = segments;

    if (
        segments.length !== 1 ||
        segment.type !== 'text' ||
        segment.text.length > maxEmojiOnlyLength ||
        /[a-z]/i.test(segment.text.replace(shortcodeLike, ''))
    ) {
        return 0;
    }

    const support = supportedEmoji();

    if (!support) {
        return 0;
    }

    let count = 0;

    for (const { segment: grapheme } of support.graphemes.segment(
        emojify(segment.text),
    )) {
        if (grapheme.trim() === '') {
            continue;
        }

        if (!support.singleEmoji.test(grapheme)) {
            return 0;
        }

        count++;
    }

    return count;
}

/**
 * How many emoji a body is made of when it is nothing but one to three of them, so it can show them large; 0 otherwise.
 */
export function jumboEmojiCount(segments: BodySegment[]): 0 | 1 | 2 | 3 {
    const count = emojiOnlyCount(segments);

    return count > maxJumboEmoji ? 0 : (count as 0 | 1 | 2 | 3);
}

export function emojifyInput(
    text: string,
    caret: number,
): { text: string; caret: number } {
    if (!text.includes(':')) {
        return { text, caret };
    }

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
 * Each emoji once, under its best matching shortcode: the ones starting with the query, then the ones with a later
 * word starting with it, then the ones that only contain it. Within each, the emoji the user uses most come first,
 * then the shortest shortcodes. Colons around the query and its case are ignored.
 */
export function searchEmoji(
    query: string,
    uses: ReadonlyMap<string, number> = new Map(),
): EmojiSuggestion[] {
    const partial = query
        .trim()
        .toLowerCase()
        .replace(/^:+|:+$/g, '');
    const best = new Map<string, { name: string; rank: number }>();

    for (const name of shortcodeNames) {
        if (!name.includes(partial)) {
            continue;
        }

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
    }

    return [...best.entries()]
        .sort(
            ([leftEmoji, left], [rightEmoji, right]) =>
                left.rank - right.rank ||
                (uses.get(rightEmoji) ?? 0) - (uses.get(leftEmoji) ?? 0) ||
                left.name.length - right.name.length ||
                left.name.localeCompare(right.name),
        )
        .map(([emoji, { name }]) => ({ name, emoji }));
}
