import type { SharedClock } from '@/lib/utils';
import type { Auth, SuggestedUser, User } from '@/types/auth';
import type { Commit } from '@/types/commit';
import type {
    CommittedFlash,
    DeletedFlash,
    FlashToast,
    TimeoutFlash,
} from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            timeout?: TimeoutFlash;
            achievementsUnlocked?: string[];
            committed?: CommittedFlash;
            deleted?: DeletedFlash;
            restored?: Commit;
            scheduled?: boolean;
            submitted?: string;
            localSignIn?: boolean;
            signedUp?: boolean;
            claimFailed?: { message: string; githubUrl: string | null };
            lookupThrottled?: true;
        };
        sharedPageProps: {
            name: string;
            repository: string;
            auth: Auth;
            /** For guests: how many developers are here, rounded down (12,000). */
            developersCount: number | null;
            hasUnmoderatedCommits: boolean;
            sidebarOpen: boolean;
            showsWhoToFollow: boolean;
            dimsLanes: boolean;
            repliesPerPage: number;
            clock: SharedClock;
            /** When the server rendered the page, in milliseconds. */
            now: number;
            suggestedUsers?: SuggestedUser[];
            liveFollowing?: User[];
            /** When each stream on now runs out, by person, for streams pages show without their end. */
            liveDeadlines?: Record<string, string>;
            hotTopics?: { id: string; name: string; summary: string | null }[];
            [key: string]: unknown;
        };
    }
}
