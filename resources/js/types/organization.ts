import type { Lane } from '@/types/auth';

/**
 * An organization, in /dev/null's blue until its admins choose a lane.
 */
export type Organization = {
    id: string;
    handle: string;
    name: string;
    avatar_url: string | null;
    bio: string | null;
    location: string | null;
    website: string | null;
    lane: Lane | null;
};
