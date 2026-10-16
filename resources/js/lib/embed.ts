import { hostname } from '@/lib/utils';

const commandPattern =
    /^([\s\S]*?)(?<!\S)(\/embed[ \t]+(\S+)(?:[ \t]+(\S+))?)\s*$/;
const ratioPattern = /^([1-9]\d{0,2}):([1-9]\d{0,2})$/;

export type EmbedCommand = {
    start: number;
    text: string;
    url: string;
    ratio: string;
};

function isEmbeddableUrl(candidate: string): boolean {
    try {
        const url = new URL(candidate);

        return (
            url.protocol === 'https:' &&
            url.username === '' &&
            url.password === '' &&
            (typeof window === 'undefined' ||
                url.hostname !== window.location.hostname)
        );
    } catch {
        return false;
    }
}

function isEmbeddableRatio(candidate: string): boolean {
    const match = ratioPattern.exec(candidate);
    const ratio = match ? Number(match[1]) / Number(match[2]) : 0;

    return ratio >= 1 / 4 && ratio <= 4;
}

/**
 * Finds the "/embed <url> [<width>:<height>]" command at the end of a body, only when it will embed once committed.
 * Keep the rules in sync with app/Services/Embeds.php and app/Rules/ValidEmbed.php.
 */
export function findEmbedCommand(body: string): EmbedCommand | null {
    const match = commandPattern.exec(body);

    if (!match) {
        return null;
    }

    const [, before, text, url, ratio = '1:1'] = match;

    if (!isEmbeddableUrl(url) || !isEmbeddableRatio(ratio)) {
        return null;
    }

    return { start: before.length, text, url, ratio };
}

/**
 * Whether the body ends partway through an "/embed" line, where a colon starts a ratio like 4:3 rather than an emoji.
 */
export function endsInEmbedCommand(body: string): boolean {
    return /(?<!\S)\/embed[ \t][^\n]*$/.test(body);
}

/**
 * The body as it reads in a single line of text: an embed without a message stands in as "/embed <host>". Keep in
 * sync with Commit::summary().
 */
export function commitSummary(commit: {
    body: string;
    embed_url?: string | null;
}): string {
    return commit.body === '' && commit.embed_url
        ? `/embed ${hostname(commit.embed_url)}`
        : commit.body;
}
