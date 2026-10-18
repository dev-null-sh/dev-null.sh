import type { MessageReaction } from '@/types';

export type ReactionUsage = { emoji: string; count: number };

export type ReactionSummary = {
    emoji: string;
    count: number;
    isMine: boolean;
    userIds: string[];
};

export const defaultQuickReactions = ['😂', '✅', '❤️'];

/**
 * How many more times an emoji is used than a quick reaction before it takes that slot.
 */
const replaceMargin = 3;

function byUse(usage: ReactionUsage[]): ReactionUsage[] {
    return usage.toSorted((left, right) => right.count - left.count);
}

/**
 * The reactions one click away: joy, check and heart at first. An emoji the user reacts with takes the weakest slot,
 * in place, only once it is used clearly more than that slot, so the bar changes slowly and the rest keep their spot.
 */
export function quickReactions(usage: ReactionUsage[]): string[] {
    const uses = new Map(usage.map(({ emoji, count }) => [emoji, count]));
    const usesOf = (emoji: string) => uses.get(emoji) ?? 0;
    const slots = [...defaultQuickReactions];

    for (const { emoji, count } of byUse(usage)) {
        if (slots.includes(emoji)) {
            continue;
        }

        const weakest = slots.reduce(
            (weakestIndex, slot, index) =>
                usesOf(slot) <= usesOf(slots[weakestIndex])
                    ? index
                    : weakestIndex,
            0,
        );

        if (count >= usesOf(slots[weakest]) + replaceMargin) {
            slots[weakest] = emoji;
        }
    }

    return slots;
}

export function frequentReactions(
    usage: ReactionUsage[],
    limit: number,
): string[] {
    return byUse(usage)
        .slice(0, limit)
        .map(({ emoji }) => emoji);
}

/**
 * The usage after the user adds a reaction, or takes one back, without waiting for the server to count it again.
 */
export function withReactionUse(
    usage: ReactionUsage[],
    emoji: string,
    change: 1 | -1,
): ReactionUsage[] {
    const known = usage.some((use) => use.emoji === emoji);
    const changed = known
        ? usage.map((use) =>
              use.emoji === emoji ? { ...use, count: use.count + change } : use,
          )
        : [...usage, { emoji, count: change }];

    return changed.filter((use) => use.count > 0);
}

/**
 * One chip per emoji, in the order each was first left.
 */
export function summarizeReactions(
    reactions: MessageReaction[] | undefined,
    userId: string,
): ReactionSummary[] {
    const byEmoji = new Map<string, ReactionSummary>();

    for (const reaction of reactions ?? []) {
        const summary = byEmoji.get(reaction.emoji) ?? {
            emoji: reaction.emoji,
            count: 0,
            isMine: false,
            userIds: [],
        };

        byEmoji.set(reaction.emoji, {
            ...summary,
            count: summary.count + 1,
            isMine: summary.isMine || reaction.user_id === userId,
            userIds: [...summary.userIds, reaction.user_id],
        });
    }

    return [...byEmoji.values()];
}

export type Tap = { x: number; y: number; time: number };

/**
 * The emoji a double tap on a message leaves, or takes back.
 */
export const doubleTapReaction = '❤️';

const doubleTapWithin = 300;

const doubleTapDistance = 24;

/**
 * Whether a tap lands soon enough after, and close enough to, the one before to count as a double tap.
 */
export function isDoubleTap(previous: Tap | null, tap: Tap): boolean {
    return (
        previous !== null &&
        tap.time - previous.time <= doubleTapWithin &&
        Math.hypot(tap.x - previous.x, tap.y - previous.y) <= doubleTapDistance
    );
}
