import { mapTypedProse, typedProse } from '@/lib/code';

export type TextExpansion = {
    name: string;
    text: string;
};

/**
 * What a "/command" stands for. Adding one here is enough: it is suggested and expanded wherever emoji shortcodes
 * are, and listed after the issue commands in the composer. Commands are lowercase slugs.
 */
export const textExpansions: TextExpansion[] = [
    { name: '/shrug', text: '¯\\_(ツ)_/¯' },
    { name: '/tableflip', text: '(╯°□°)╯︵ ┻━┻' },
];

const textByName = new Map(
    textExpansions.map(({ name, text }) => [name, text]),
);

const commandPattern = new RegExp(
    `(^|\\s)(${textExpansions.map(({ name }) => name).join('|')})(?=\\s)`,
    'g',
);

export function expansionsStartingWith(typed: string): TextExpansion[] {
    return textExpansions.filter(({ name }) => name.startsWith(typed));
}

/**
 * The "/command" being typed in prose right before the caret, at the start of the text or after a space, while an
 * expansion still starts with it: where its slash is, and what follows it.
 */
export function expansionCommandBeforeCaret(
    before: string,
): { start: number; query: string } | null {
    const match = (typedProse(before).at(-1) ?? '').match(
        /(?:^|\s)(\/([a-z0-9-]*))$/,
    );

    const start = before.length - (match?.[1].length ?? 0);

    return match &&
        startsAtBoundary(before, start) &&
        expansionsStartingWith(match[1]).length > 0
        ? { start, query: match[2] }
        : null;
}

/**
 * Whether a slash starts the text or follows a space in it. A prose part can start right after code, so its own start
 * is no boundary.
 */
function startsAtBoundary(text: string, slash: number): boolean {
    return slash === 0 || /\s/u.test(text.charAt(slash - 1));
}

/**
 * The expansions a draft could still become while it is only a slash and the start of a command, like the issue
 * commands.
 */
export function suggestedExpansions(body: string): TextExpansion[] {
    return /^\/[a-z0-9-]*$/.test(body) ? expansionsStartingWith(body) : [];
}

/**
 * A command typed at the start of the text or after a space becomes its text once a space or a new line ends it, as on
 * Slack. Code is left as typed. A caret after a command moves with its text, and one inside it goes to the text's end.
 */
export function expandTextCommands(
    text: string,
    caret: number,
): { text: string; caret: number } {
    if (!text.includes('/')) {
        return { text, caret };
    }

    let shift = 0;
    let movedCaret = caret;

    const expanded = mapTypedProse(text, (prose, offset) =>
        prose.replace(
            commandPattern,
            (match: string, before: string, name: string, index: number) => {
                const start = offset + index + before.length;

                if (!startsAtBoundary(text, start)) {
                    return match;
                }

                const replacement = textByName.get(name) ?? name;
                const end = start + name.length;

                if (caret >= end) {
                    movedCaret += replacement.length - name.length;
                } else if (caret > start) {
                    movedCaret = start + shift + replacement.length;
                }

                shift += replacement.length - name.length;

                return before + replacement;
            },
        ),
    );

    return { text: expanded, caret: movedCaret };
}
