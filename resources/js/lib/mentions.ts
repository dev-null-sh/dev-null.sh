// Keep in sync with app/Actions/ParseMentions.php.

/** Full @username for rendering (alphanumeric, hyphens, periods; not starting/ending with - or .). */
export const mentionPattern =
    /(?<![\p{L}\p{N}_])@([A-Za-z0-9](?:[A-Za-z0-9.-]{0,37}[A-Za-z0-9])?)(?![\p{L}\p{N}-])/gu;

/** Active @token at the caret while typing (query may be empty or incomplete). */
const activeMentionPattern =
    /(?<![\p{L}\p{N}_])@([A-Za-z0-9.-]{0,39})$/u;

export type ActiveMention = {
    start: number;
    end: number;
    query: string;
};

export function getActiveMention(
    text: string,
    caret: number,
): ActiveMention | null {
    const before = text.slice(0, caret);
    const match = before.match(activeMentionPattern);

    if (!match || match.index === undefined) {
        return null;
    }

    const at = match[0].indexOf('@');

    return {
        start: match.index + at,
        end: caret,
        query: match[1] ?? '',
    };
}

export function insertMention(
    text: string,
    mention: ActiveMention,
    username: string,
): { value: string; caret: number } {
    const insertion = `@${username} `;
    const value =
        text.slice(0, mention.start) + insertion + text.slice(mention.end);

    return {
        value,
        caret: mention.start + insertion.length,
    };
}
