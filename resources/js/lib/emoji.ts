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

export function suggestEmoji(text: string): EmojiSuggestion | null {
    const match = /:([A-Za-z0-9_+-]*)$/.exec(text);

    if (match === null || match[1] === '') {
        return null;
    }

    const partial = match[1].toLowerCase();

    let best: { name: string; emoji: string } | null = null;

    for (const [name, emoji] of Object.entries(shortcodeEmoji)) {
        if (
            name.startsWith(partial) &&
            (best === null || name.length < best.name.length)
        ) {
            best = { name, emoji };
        }
    }

    return best;
}

export function completeEmoji(text: string): string | null {
    const suggestion = suggestEmoji(text);

    if (suggestion === null) {
        return null;
    }

    return text.replace(/:([A-Za-z0-9_+-]*)$/, suggestion.emoji);
}
