export type ThreadPosition = {
    position: number;
    size: number;
    isCapped: boolean;
};

export function ThreadPositionBadge({
    threadPosition,
}: {
    threadPosition?: ThreadPosition;
}) {
    if (threadPosition === undefined) {
        return null;
    }

    const { position, size, isCapped } = threadPosition;

    return (
        <span
            className="shrink-0 self-center rounded-sm bg-muted px-2 py-0.5 font-mono text-xs text-muted-foreground tabular-nums"
            title={`Commit ${position} of ${size}${isCapped ? ' or more' : ''} in this thread`}
            data-test="thread-position"
        >
            {position}/{size}
            {isCapped && '+'}
        </span>
    );
}
