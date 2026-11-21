import type { Guild } from '@/types/guild';

/**
 * The guild a hashtag leads to: the one with that handle, or with it as an alias.
 */
export function guildFor<T extends Pick<Guild, 'handle' | 'aliases'>>(
    guilds: T[] | null | undefined,
    handle: string,
): T | undefined {
    return guilds?.find(
        (guild) => guild.handle === handle || guild.aliases.includes(handle),
    );
}
