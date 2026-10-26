import type { CommitChannel } from '@/types';

const descriptions: Record<CommitChannel, string> = {
    cli: 'Posted from a terminal',
    ci: 'Posted from a workflow',
    ai: 'AI-generated content posted on the author’s behalf',
    bot: 'An automated reply from a platform bot',
};

export function CommitChannelBadges({
    channels = [],
}: {
    channels?: CommitChannel[];
}) {
    if (channels.length === 0) {
        return null;
    }

    return (
        <span className="inline-flex shrink-0 items-center gap-1 self-center">
            {channels.map((channel) => (
                <span
                    key={channel}
                    className="rounded-sm border px-1 font-mono text-[10px] leading-4 text-muted-foreground"
                    title={descriptions[channel]}
                    aria-label={`${channel}: ${descriptions[channel]}`}
                    data-test="commit-channel"
                >
                    {channel}
                </span>
            ))}
        </span>
    );
}
