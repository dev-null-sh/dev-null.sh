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

    return match && expansionsStartingWith(match[1]).length > 0
        ? { start: before.length - match[1].length, query: match[2] }
        : null;
}

/**
 * The expansions a draft could still become while it is only a slash and the start of a command, like the issue
 * commands.
 */
export function suggestedExpansions(body: string): TextExpansion[] {
    return /^\/[a-z0-9-]*$/.test(body) ? expansionsStartingWith(body) : [];
}

function expand(text: string): string {
    if (!text.includes('/')) {
        return text;
    }

    return mapTypedProse(text, (prose) =>
        prose.replace(
            commandPattern,
            (_, before: string, name: string) => before + textByName.get(name),
        ),
    );
}

/**
 * A command typed at the start of the text or after a space becomes its text once a space or a new line ends it, as on
 * Slack. Code is left as typed.
 */
export function expandTextCommands(
    text: string,
    caret: number,
): { text: string; caret: number } {
    const next = text.charAt(caret);

    return {
        text: expand(text),
        caret: expand(text.slice(0, caret) + next).length - next.length,
    };
}
