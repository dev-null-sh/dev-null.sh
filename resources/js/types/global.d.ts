import type { Auth, SuggestedUser } from '@/types/auth';
import type { Commit } from '@/types/commit';
import type { CommittedFlash, DeletedFlash, FlashToast } from '@/types/ui';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        flashDataType: {
            toast?: FlashToast;
            achievementsUnlocked?: string[];
            committed?: CommittedFlash;
            deleted?: DeletedFlash;
            restored?: Commit;
            scheduled?: boolean;
            localSignIn?: boolean;
            signedUp?: boolean;
        };
        sharedPageProps: {
            name: string;
            auth: Auth;
            hasUnmoderatedCommits: boolean;
            sidebarOpen: boolean;
            showsWhoToFollow: boolean;
            dimsLanes: boolean;
            repliesPerPage: number;
            suggestedUsers?: SuggestedUser[];
            [key: string]: unknown;
        };
    }
}
