import { cn } from '@/lib/utils';
import type { Guild } from '@/types/guild';

export function GuildIcon({
    guild,
    className,
}: {
    guild: Pick<Guild, 'emoji' | 'icon_url'>;
    className?: string;
}) {
    return (
        <span
            aria-hidden
            className={cn(
                'inline-flex size-5 shrink-0 items-center justify-center overflow-hidden rounded-sm bg-muted',
                className,
            )}
        >
            {guild.icon_url ? (
                <img
                    src={guild.icon_url}
                    alt=""
                    className="size-full object-cover"
                />
            ) : (
                guild.emoji
            )}
        </span>
    );
}
