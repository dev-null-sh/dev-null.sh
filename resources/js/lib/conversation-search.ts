import type { ConversationSummary, User } from '@/types';

/**
 * Everything the server found for the term, then the conversations on hand that the inbox search would also find:
 * by a partner's or member's name or handle, or an org's name. A conversation shows once, as its first copy.
 */
export function conversationsMatching(
    term: string,
    onHand: ConversationSummary[],
    found: ConversationSummary[] = [],
): ConversationSummary[] {
    const needle = term.toLowerCase();
    const isMatch = (value: string | null) =>
        value?.toLowerCase().includes(needle) ?? false;
    const isPersonMatch = ({ handle, name }: User) =>
        isMatch(handle) || isMatch(name);

    return [
        ...found,
        ...onHand.filter(
            (conversation) =>
                (conversation.partner !== null &&
                    isPersonMatch(conversation.partner)) ||
                isMatch(conversation.name) ||
                (conversation.members ?? []).some(({ user }) =>
                    isPersonMatch(user),
                ),
        ),
    ].filter(
        (conversation, index, all) =>
            all.findIndex(({ id }) => id === conversation.id) === index,
    );
}
