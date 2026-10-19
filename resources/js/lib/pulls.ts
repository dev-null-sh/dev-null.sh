import type { PendingMessage } from '@/lib/chat';
import type { Message, OrgMember, User } from '@/types';

/**
 * The message whose push and pull status shows: the latest one in an org, or my latest one in a conversation.
 */
export function statusMessageOf(
    messages: Message[],
    pending: PendingMessage[],
    user: User,
    isOrg: boolean,
): string | null {
    const latestPending = pending.at(-1);

    if (latestPending) {
        return latestPending.client_id;
    }

    return (
        messages.findLast(
            (message) =>
                !message.event && (isOrg || message.user_id === user.id),
        )?.client_id ?? null
    );
}

/**
 * The org members who pulled a message, out of everyone who could, leaving out its author.
 */
export function pullsOf(
    members: OrgMember[],
    message: Pick<Message, 'id' | 'user_id'>,
): { pulledBy: User[]; total: number } {
    const readers = members.filter(
        (member) => member.user.id !== message.user_id,
    );

    return {
        pulledBy: readers
            .filter((member) => member.read_through_id >= message.id)
            .map((member) => member.user),
        total: readers.length,
    };
}
