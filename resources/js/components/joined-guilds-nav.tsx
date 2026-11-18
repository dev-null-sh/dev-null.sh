import { Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildIcon } from '@/components/guild-icon';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { inOpeningOrder } from '@/lib/joined-guilds-order';
import { cn } from '@/lib/utils';
import type { JoinedGuild } from '@/types/guild';

function missedCommitsLabel(count: number): string {
    return `${count} missed ${count === 1 ? 'commit' : 'commits'}`;
}

function JoinedGuildLink({
    guild,
    iconsOnly,
}: {
    guild: JoinedGuild;
    iconsOnly: boolean;
}) {
    const { isCurrentUrl } = useCurrentUrl();
    const href = show(guild.handle);
    const isActive = isCurrentUrl(href);
    const missed = isActive ? 0 : guild.missed_commits;
    const label =
        missed > 0
            ? `${guild.name}, ${missedCommitsLabel(missed)}`
            : guild.name;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={href}
                    prefetch
                    cacheFor="10s"
                    viewTransition
                    aria-label={label}
                    aria-current={isActive ? 'page' : undefined}
                    title={iconsOnly ? undefined : guild.name}
                    className={cn(
                        'relative flex h-10 items-center gap-3 rounded-lg pr-3 pl-3.5 text-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        {
                            'font-medium text-foreground': missed > 0,
                            'bg-primary-soft text-primary hover:bg-primary-soft hover:text-primary':
                                isActive,
                        },
                    )}
                    data-test="joined-guild"
                >
                    <span className="relative flex shrink-0">
                        <GuildIcon guild={guild} className="size-6 text-sm" />
                        {missed > 0 && (
                            <span
                                aria-hidden
                                className="absolute -top-1 -right-1 size-2 rounded-full bg-primary ring-2 ring-background nav-wide:hidden"
                            />
                        )}
                    </span>
                    <span className="hidden min-w-0 flex-1 truncate nav-wide:block">
                        {guild.name}
                    </span>
                    {missed > 0 && (
                        <span
                            aria-hidden
                            title={missedCommitsLabel(missed)}
                            className="hidden shrink-0 font-mono text-[11px] leading-4 text-primary tabular-nums nav-wide:block"
                        >
                            {missed > 99 ? '99+' : missed}
                        </span>
                    )}
                </Link>
            </TooltipTrigger>
            {iconsOnly && <TooltipContent side="right">{label}</TooltipContent>}
        </Tooltip>
    );
}

export function JoinedGuildsNav({ iconsOnly }: { iconsOnly: boolean }) {
    const { joinedGuilds } = usePage().props;
    const [openingOrder] = useState(() =>
        joinedGuilds.map((guild) => guild.handle),
    );

    if (joinedGuilds.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="joined-guilds-heading"
            className="mt-3 flex flex-col gap-0.5 border-t pt-3"
        >
            <h2
                id="joined-guilds-heading"
                className="sr-only px-4 pb-1 font-mono text-xs font-medium text-muted-foreground nav-wide:not-sr-only"
            >
                Your guilds
            </h2>
            {inOpeningOrder(joinedGuilds, openingOrder).map((guild) => (
                <JoinedGuildLink
                    key={guild.handle}
                    guild={guild}
                    iconsOnly={iconsOnly}
                />
            ))}
        </section>
    );
}
