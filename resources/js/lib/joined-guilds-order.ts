import type { JoinedGuild } from '@/types/guild';

export function inOpeningOrder(
    guilds: JoinedGuild[],
    openingOrder: string[],
): JoinedGuild[] {
    const position = (guild: JoinedGuild): number => {
        const index = openingOrder.indexOf(guild.handle);

        return index === -1 ? openingOrder.length : index;
    };

    return [...guilds].sort((a, b) => position(a) - position(b));
}
