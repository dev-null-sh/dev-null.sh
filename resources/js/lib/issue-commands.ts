export type IssueKind = 'feature' | 'bug';

type CommandShape = {
    name: string;
    description: string;
};

export type IssueCommand = CommandShape & {
    kind: IssueKind;
    placeholder: string;
};

export type GifCommand = CommandShape & {
    kind: 'gif';
};

export type SlashCommand = IssueCommand | GifCommand;

export type SlashCommandKind = SlashCommand['kind'];

type CommandOptions = { canAttachGif?: boolean };

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

export const gifCommand: GifCommand = {
    kind: 'gif',
    name: '/gif',
    description: 'Attach a GIF',
};

function availableCommands({
    canAttachGif = false,
}: CommandOptions): SlashCommand[] {
    return canAttachGif ? [...issueCommands, gifCommand] : issueCommands;
}

/**
 * The commands a draft could still become while it is only a slash and the start of a name.
 */
export function suggestedCommands(
    body: string,
    options: CommandOptions = {},
): SlashCommand[] {
    return /^\/[a-z]*$/.test(body)
        ? availableCommands(options).filter((command) =>
              command.name.startsWith(body),
          )
        : [];
}

/**
 * A complete command followed by a space or a new line. Issue commands keep the rest of the draft as their body;
 * `/gif` only matches on its own so it can never discard text.
 */
export function typedCommand(
    body: string,
    { canAttachGif = false }: CommandOptions = {},
): { kind: SlashCommandKind; body: string } | null {
    if (canAttachGif && /^\/gif\s+$/.test(body)) {
        return { kind: 'gif', body: '' };
    }

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
