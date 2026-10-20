export type IssueKind = 'feature' | 'bug';

export type IssueCommand = {
    kind: IssueKind;
    name: string;
    description: string;
    placeholder: string;
};

export const issueCommands: IssueCommand[] = [
    {
        kind: 'feature',
        name: '/feature',
        description: 'Request a feature',
        placeholder:
            '# Feature title\n\n## Problem\nWhat is missing?\n\n## Proposal\nHow should it work?',
    },
    {
        kind: 'bug',
        name: '/bug',
        description: 'Report a bug',
        placeholder:
            '# Bug title\n\n## Steps\n1. How to reproduce it\n\n## Expected vs. actual\nWhat should happen, and what happens instead?',
    },
];

/**
 * The commands a draft could still become while it is only a slash and the start of a name.
 */
export function suggestedCommands(body: string): IssueCommand[] {
    return /^\/[a-z]*$/.test(body)
        ? issueCommands.filter((command) => command.name.startsWith(body))
        : [];
}

/**
 * A complete command followed by a space or a new line turns the draft into an issue, without the command.
 */
export function typedCommand(
    body: string,
): { kind: IssueKind; body: string } | null {
    const match = /^\/(feature|bug)\s/.exec(body);

    return match
        ? { kind: match[1] as IssueKind, body: body.slice(match[0].length) }
        : null;
}

/**
 * The command a plain draft seems to be about, when it mentions a bug or a feature, so it can be suggested.
 */
export function hintedCommand(body: string): IssueCommand | null {
    if (body.startsWith('/')) {
        return null;
    }

    const match = /\b(bugs?|features?)\b/i.exec(body);

    if (match === null) {
        return null;
    }

    const kind: IssueKind = match[1].toLowerCase().startsWith('bug')
        ? 'bug'
        : 'feature';

    return issueCommands.find((command) => command.kind === kind) ?? null;
}
