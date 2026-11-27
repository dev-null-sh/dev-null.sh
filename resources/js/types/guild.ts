export type Guild = {
    id: string;
    handle: string;
    name: string;
    description: string;
    emoji: string;
    icon_url: string | null;
    member_count: number;
    is_joined: boolean;
    is_muted: boolean;
    is_paused: boolean;
};

export type JoinedGuild = Pick<
    Guild,
    'handle' | 'name' | 'emoji' | 'icon_url'
> & {
    missed_commits: number;
};

export type GuildBadge = Pick<Guild, 'handle' | 'name' | 'emoji' | 'icon_url'>;

export type GuildMentions = Record<string, GuildBadge>;
