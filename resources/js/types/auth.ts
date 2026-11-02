import type { OrganizationAuthor } from '@/types/commit';
import type { RemotesFrom } from '@/types/conversation';
import type { Log } from '@/types/log';

/**
 * A lane from the palette, or a custom hex color.
 */
export type Lane = number | string;

export type LiveStream = {
    url: string;
    thumbnail_url: string;
};

export type User = {
    id: string;
    handle: string;
    name: string;
    avatar_url: string | null;
    bio: string | null;
    location: string | null;
    website: string | null;
    company: string | null;
    has_sponsors_listing: boolean;
    has_github_account: boolean;
    lane: Lane;
    timezone: string | null;
    show_local_time: boolean;
    /**
     * Only set while the user is streaming.
     */
    live?: LiveStream | null;
    created_at: string;
    updated_at: string;
};

export type Profile = User & {
    commits_count: number;
    followers_count: number;
    following_count: number;
};

/**
 * Commits and profiles are public, so guests browse them too, without a user or a log.
 */
export type Auth = {
    user: Profile | null;
    bioProfiles: Record<string, string>;
    /**
     * The organizations the user is a member of, whose commits are theirs too.
     */
    organizations: OrganizationAuthor[];
    // Whether members may post as their organization yet, while it is being rolled out.
    canPostAsOrganization: boolean;
    isAdmin: boolean;
    automaticTranslation: boolean;
    mustReadCommitRules: boolean;
    termsToAccept: string | null;
    log: Log | null;
    unreadNotificationsCount: number;
    unreadMessagesCount: number;
    remotes: {
        from: RemotesFrom;
        pull_receipts: boolean;
        requests: boolean;
        everyone: boolean;
    } | null;
    pasteis: { remaining: number; day: string } | null;
};

export type SuggestedUser = User & {
    followed_by_following_count: number;
    followers_count: number;
    follows_you: boolean;
};
