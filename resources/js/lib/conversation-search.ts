import type { ConversationSummary, User } from '@/types';

/**
 * The conversations already on hand that the inbox search would also find: by a partner's or member's name or handle,
 * or an org's name. A conversation in several lists shows once, as its first copy.
 */
export function conversationsMatching(
    term: string,
    ...lists: ConversationSummary[][]
): ConversationSummary[] {
    const needle = term.toLowerCase();
    const isMatch = (value: string | null) =>
        value?.toLowerCase().includes(needle) ?? false;
    const isPersonMatch = ({ username, name }: User) =>
        isMatch(username) || isMatch(name);

    return lists
        .flat()
        .filter(
            (conversation, index, all) =>
                all.findIndex(({ id }) => id === conversation.id) === index &&
                ((conversation.partner !== null &&
                    isPersonMatch(conversation.partner)) ||
                    isMatch(conversation.name) ||
                    (conversation.members ?? []).some(({ user }) =>
                        isPersonMatch(user),
                    )),
        );
}
