import { Deferred, Link, usePage } from '@inertiajs/react';
import { UserCheck, UserPlus } from 'lucide-react';
import { index, show } from '@/actions/App/Http/Controllers/GuildController';
import { GuildIcon } from '@/components/guild-icon';
import { useGuildPreference } from '@/components/guild-preferences';
import { Skeleton } from '@/components/ui/skeleton';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { formatCount, formatExactCount } from '@/lib/numbers';
import { cn } from '@/lib/utils';
import type { Guild, SuggestedGuild } from '@/types/guild';

export function reasonFor(
    guild: Guild,
    suggestion: SuggestedGuild,
    format = formatCount,
): string {
    if (suggestion.followed_members_count > 0) {
        return `Joined by ${format(suggestion.followed_members_count)} you follow`;
    }

    return guild.member_count === 1
        ? '1 member'
        : `${format(guild.member_count)} members`;
}

export function GuildSuggestionRow({
    guild,
    suggestion,
}: {
    guild: Guild;
    suggestion: SuggestedGuild;
}) {
    const { processing, update } = useGuildPreference(guild);
    const reason = reasonFor(guild, suggestion);
    const Icon = guild.is_joined ? UserCheck : UserPlus;

    return (
        <li data-test="suggested-guild">
            <div className="flex items-center gap-3">
                <Link
                    href={show(guild.handle)}
                    prefetch
                    className="group flex min-w-0 flex-1 items-center gap-3"
                >
                    <GuildIcon
                        guild={guild}
                        className="size-9 rounded-md text-xl"
                    />
                    <span className="min-w-0">
                        <span
                            className="block truncate text-sm font-semibold group-hover:underline"
                            title={guild.name}
                        >
                            {guild.name}
                        </span>
                        <span
                            className="block truncate font-mono text-xs text-muted-foreground"
                            title={`#${guild.handle}`}
                        >
                            #{guild.handle}
                        </span>
                    </span>
                </Link>
                <Tooltip>
                    <TooltipTrigger asChild>
                        <button
                            type="button"
                            aria-disabled={processing}
                            onClick={() => update('joined')}
                            aria-label={`${guild.is_joined ? 'Leave' : 'Join'} ${guild.name}`}
                            className={cn(
                                'shrink-0 rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                {
                                    'text-primary hover:text-primary':
                                        guild.is_joined,
                                },
                            )}
                            data-test="suggested-guild-join"
                        >
                            <Icon aria-hidden className="size-4" />
                        </button>
                    </TooltipTrigger>
                    <TooltipContent>
                        {guild.is_joined
                            ? `Joined ${guild.name}`
                            : `Join ${guild.name}`}
                    </TooltipContent>
                </Tooltip>
            </div>
            <div className="mt-1.5 min-h-8 pl-12">
                {guild.description && (
                    <span
                        className="block truncate text-xs text-muted-foreground"
                        title={guild.description}
                    >
                        {guild.description}
                    </span>
                )}
                <span
                    className="block truncate text-xs text-muted-foreground"
                    title={reasonFor(guild, suggestion, formatExactCount)}
                >
                    {reason}
                </span>
            </div>
        </li>
    );
}

const shownGuildsCount = 3;

function GuildSuggestionsSkeleton({ count }: { count: number }) {
    return (
        <>
            {Array.from({ length: count }, (_, index) => (
                <li key={index} aria-hidden>
                    <div className="flex items-center gap-3">
                        <Skeleton className="size-9 shrink-0 rounded-md" />
                        <span className="flex-1">
                            <Skeleton className="my-[3px] h-3.5 w-28" />
                            <Skeleton className="my-0.5 h-3 w-20" />
                        </span>
                        <Skeleton className="size-7 shrink-0" />
                    </div>
                    <div className="mt-1.5 min-h-8 pl-12">
                        <Skeleton className="my-0.5 h-3 w-36" />
                        <Skeleton className="my-0.5 h-3 w-32" />
                    </div>
                </li>
            ))}
        </>
    );
}

export function GuildSuggestions() {
    const { guilds, suggestedGuilds } = usePage().props;

    if (guilds === null) {
        return null;
    }

    const suggestable = guilds.filter(
        (guild) => !guild.is_joined && !guild.is_muted && !guild.is_paused,
    ).length;
    const rows = (suggestedGuilds ?? []).flatMap((suggestion) => {
        const guild = guilds.find((item) => item.id === suggestion.id);

        return guild && !guild.is_muted && !guild.is_paused
            ? [{ guild, suggestion }]
            : [];
    });

    if (suggestedGuilds === undefined ? suggestable === 0 : rows.length === 0) {
        return null;
    }

    return (
        <section
            aria-labelledby="aside-guild-suggestions"
            className="-mt-4 border-t pt-4"
            data-test="guild-suggestions"
        >
            <h2
                id="aside-guild-suggestions"
                className="mb-3 flex min-h-7 items-center text-sm font-semibold"
            >
                Guilds for you
            </h2>
            <ul className="space-y-4">
                <Deferred
                    data="suggestedGuilds"
                    fallback={
                        <GuildSuggestionsSkeleton
                            count={Math.min(shownGuildsCount, suggestable)}
                        />
                    }
                >
                    {rows.map(({ guild, suggestion }) => (
                        <GuildSuggestionRow
                            key={guild.id}
                            guild={guild}
                            suggestion={suggestion}
                        />
                    ))}
                </Deferred>
            </ul>
            <Link
                href={index()}
                prefetch
                className="mt-3 inline-block text-sm text-primary hover:underline"
            >
                Browse all guilds
            </Link>
        </section>
    );
}
