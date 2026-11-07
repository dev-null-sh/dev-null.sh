import type { ThreadCommit } from '@/types';

const positionOf = (reply: ThreadCommit): [string, string] => [
    'created_at' in reply ? reply.created_at : '',
    reply.id,
];

const compare = (a: ThreadCommit, b: ThreadCommit): number => {
    const [aCreatedAt, aId] = positionOf(a);
    const [bCreatedAt, bId] = positionOf(b);

    return aCreatedAt === bCreatedAt
        ? aId.localeCompare(bId)
        : aCreatedAt.localeCompare(bCreatedAt);
};

/**
 * Brings the replies a thread heard changed into what it already loaded, without reading the rest again: a reply that
 * changed is replaced where it is, one the viewer may no longer see goes, and one they can see now joins in its place
 * when that place is within what was loaded, as when an older pending reply is approved. Newer ones past the loaded
 * pages wait for the reader to load them.
 */
export function reconcileReplies(
    loaded: ThreadCommit[],
    hasMore: boolean,
    requested: string[],
    fresh: ThreadCommit[],
    newest: boolean,
): ThreadCommit[] {
    const byId = new Map(fresh.map((reply) => [reply.id, reply]));
    const order = (a: ThreadCommit, b: ThreadCommit) =>
        newest ? compare(b, a) : compare(a, b);
    const kept = loaded.flatMap((reply) =>
        !requested.includes(reply.id) ? [reply] : (byId.get(reply.id) ?? []),
    );
    const last = loaded.at(-1);
    const joining = fresh.filter(
        (reply) =>
            !loaded.some(({ id }) => id === reply.id) &&
            (!hasMore || last === undefined || order(reply, last) < 0),
    );

    return joining.reduce((replies, reply) => {
        const index = replies.findIndex((other) => order(reply, other) < 0);

        return index === -1
            ? [...replies, reply]
            : [...replies.slice(0, index), reply, ...replies.slice(index)];
    }, kept);
}
