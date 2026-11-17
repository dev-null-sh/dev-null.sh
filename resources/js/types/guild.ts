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
};
