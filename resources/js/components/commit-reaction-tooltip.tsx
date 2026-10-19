import type { ReactElement } from 'react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { emojiName } from '@/lib/emoji';
import type { Commit } from '@/types';

export function CommitReactionTooltip({
    reaction,
    viewerId,
    privateOnly = false,
    children,
}: {
    reaction: NonNullable<Commit['reactions']>[number];
    viewerId?: string;
    privateOnly?: boolean;
    children: ReactElement;
}) {
    if (reaction.count === 0) return children;
    const names = privateOnly
        ? ['You']
        : (reaction.users ?? []).map((user) =>
              user.id === viewerId ? 'You' : user.name || `@${user.username}`,
          );
    const others = reaction.count - names.length;
    if (others > 0)
        names.push(`${others} ${others === 1 ? 'other' : 'others'}`);
    const people = new Intl.ListFormat('en', {
        style: 'long',
        type: 'conjunction',
    }).format(names);
    return (
        <Tooltip>
            <TooltipTrigger asChild>{children}</TooltipTrigger>
            <TooltipContent className="max-w-64 text-center">
                <span aria-hidden className="mb-2 block text-4xl leading-none">
                    {reaction.emoji}
                </span>
                <span>
                    {people} reacted {privateOnly ? 'privately ' : ''}with :
                    {emojiName(reaction.emoji) ?? reaction.emoji}:
                </span>
            </TooltipContent>
        </Tooltip>
    );
}
