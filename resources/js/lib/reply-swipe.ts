/**
 * How far a message is pulled before letting go replies to it.
 */
export const replyThreshold = 56;

const maxPull = 72;

/**
 * How far a message follows the finger: someone else's message is pulled right, away from its edge, and my own left.
 */
export function replyPull(dx: number, isMine: boolean): number {
    return Math.max(0, Math.min(maxPull, isMine ? -dx : dx));
}

/**
 * The first move of a touch decides it: more sideways than up or down pulls the message, anything else scrolls.
 */
export function isReplySwipe(dx: number, dy: number): boolean {
    return Math.abs(dx) > Math.abs(dy);
}
