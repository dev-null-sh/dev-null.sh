export type User = {
    id: string;
    username: string;
    name: string;
    avatar_url: string | null;
    bio: string | null;
    location: string | null;
    website: string | null;
    company: string | null;
    lane: number;
    created_at: string;
    updated_at: string;
};

export type Profile = User & {
    commits_count: number;
    followers_count: number;
    following_count: number;
};

export type Auth = {
    user: Profile;
    isAdmin: boolean;
    unreadNotificationsCount: number;
};
