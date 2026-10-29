import { usePage } from '@inertiajs/react';
import { useRef } from 'react';
import {
    resolveIsFollowing,
    toggleFollow,
    useIsFollowing,
} from '@/components/follow-button';
import { signIn } from '@/lib/auth';
import type { User } from '@/types';

/**
 * Keyboard target for `f` on a focused commit: follow or unfollow that commit's author.
 */
export function CommitAuthorFollowButton({
    user,
    keyShortcut,
}: {
    user: Pick<User, 'id' | 'handle'>;
    keyShortcut?: string;
}) {
    const { auth } = usePage().props;
    const [, setIsFollowing] = useIsFollowing(user, false);
    const isBusy = useRef(false);

    if (auth.user?.id === user.id) {
        return null;
    }

    const onClick = () => {
        if (auth.user === null) {
            signIn();

            return;
        }

        if (isBusy.current) {
            return;
        }

        isBusy.current = true;

        void resolveIsFollowing(user).then((isFollowing) => {
            if (isFollowing === null) {
                isBusy.current = false;

                return;
            }

            toggleFollow(user, isFollowing, setIsFollowing, () => {
                isBusy.current = false;
            });
        });
    };

    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={`Follow or unfollow @${user.handle}`}
            aria-keyshortcuts={keyShortcut}
            tabIndex={-1}
            className="sr-only"
            data-test="commit-follow-author-button"
        >
            Follow or unfollow @{user.handle}
        </button>
    );
}
