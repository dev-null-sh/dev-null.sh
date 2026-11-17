import type { Guild } from '@/types/guild';

/**
 * The guilds whose handle or name matches the term, closest first and then biggest. Every guild is already on the
 * page for members, so this filters them in place instead of asking the server. An "@" search is for developers, so
 * it finds no guilds; a leading "#" is how guilds are written, so it is ignored.
 */
export function matchGuilds(
    guilds: Guild[] | null | undefined,
    term: string,
    limit = 3,
): Guild[] {
    const trimmed = term.trim();

    if (!guilds || trimmed.startsWith('@')) {
        return [];
    }

    const needle = trimmed.replace(/^#/u, '').trim().toLowerCase();

    if (needle === '') {
        return [];
    }

    return guilds
        .map((guild) => ({ guild, rank: rank(guild, needle) }))
        .filter(({ rank }) => rank !== null)
        .sort(
            (a, b) =>
                a.rank! - b.rank! ||
                b.guild.member_count - a.guild.member_count ||
                a.guild.handle.localeCompare(b.guild.handle),
        )
        .slice(0, limit)
        .map(({ guild }) => guild);
}

function rank(guild: Guild, needle: string): number | null {
    const handle = guild.handle.toLowerCase();
    const name = guild.name.toLowerCase();

    if (handle === needle) {
        return 0;
    }

    if (handle.startsWith(needle)) {
        return 1;
    }

    if (name.startsWith(needle)) {
        return 2;
    }

    if (handle.includes(needle) || name.includes(needle)) {
        return 3;
    }

    return null;
}
