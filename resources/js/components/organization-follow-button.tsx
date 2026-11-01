import { router, usePage } from '@inertiajs/react';
import { ChevronDown } from 'lucide-react';
import type { ComponentProps } from 'react';
import { useRef } from 'react';
import OrganizationFollowController from '@/actions/App/Http/Controllers/OrganizationFollowController';
import {
    FollowLabel,
    useFollowHover,
    useFollow,
} from '@/components/follow-button';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { signIn } from '@/lib/auth';
import { cn } from '@/lib/utils';
import type { Organization } from '@/types';

type FollowedOrganization = Pick<Organization, 'handle'> & { id: string };

/**
 * Follows an organization, or unfollows it, sharing whether you follow it with every other control for it, like
 * following people. Each follow waits for the one before it to answer, so a double click can't follow and then
 * unfollow.
 */
function useOrganizationFollow(
    organization: FollowedOrganization,
    initialIsFollowing: boolean,
    only: string[],
    onToggled: () => void,
) {
    const { isFollowing, callbacks } = useFollow(
        organization,
        initialIsFollowing,
    );

    const toggle = () => {
        router.visit(
            isFollowing
                ? OrganizationFollowController.destroy(organization)
                : OrganizationFollowController.store(organization),
            {
                only,
                preserveState: true,
                preserveScroll: true,
                async: true,
                ...callbacks,
                onStart: (visit) => {
                    callbacks.onStart(visit);
                    onToggled();
                },
                onSuccess: () => router.flushAll(),
            },
        );
    };

    return { isFollowing, toggle };
}

/**
 * Following an organization shows what it commits. Watching, which also shows the commits that mention it, comes
 * later: the menu already offers it, so following doesn't change shape when it does.
 */
export function OrganizationFollowButton({
    organization,
    isFollowing: initialIsFollowing,
    menu = false,
    only = ['auth'],
    size,
    className,
}: {
    organization: FollowedOrganization;
    isFollowing: boolean;
    /**
     * Whether a followed organization opens the Follow and Watch menu, as on its page, instead of unfollowing.
     */
    menu?: boolean;
    only?: string[];
    size?: ComponentProps<typeof Button>['size'];
    className?: string;
}) {
    const { auth } = usePage().props;
    // Both states share it, so unfollowing from the menu keeps keyboard focus on the button that replaces it.
    const buttonRef = useRef<HTMLButtonElement>(null);
    const { isFollowing, toggle } = useOrganizationFollow(
        organization,
        initialIsFollowing,
        only,
        () =>
            requestAnimationFrame(() => {
                if (document.activeElement === document.body) {
                    buttonRef.current?.focus();
                }
            }),
    );
    const { isJustToggled, markToggled, onMouseLeave } = useFollowHover();

    if (auth.user === null) {
        return (
            <Button
                size={size}
                className={className}
                onClick={signIn}
                aria-label={`Follow @${organization.handle}`}
            >
                Follow
            </Button>
        );
    }

    if (isFollowing && menu) {
        return (
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        ref={buttonRef}
                        variant="outline"
                        size={size}
                        className={className}
                    >
                        Following
                        <ChevronDown
                            aria-hidden
                            className="size-4 opacity-60"
                        />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-72">
                    <DropdownMenuCheckboxItem
                        checked
                        onSelect={(event) => event.preventDefault()}
                        className="items-start"
                    >
                        <span className="flex min-w-0 flex-col">
                            <span className="font-medium">Follow</span>
                            <span className="text-muted-foreground">
                                What @{organization.handle} commits
                            </span>
                        </span>
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuCheckboxItem
                        checked={false}
                        disabled
                        className="items-start"
                    >
                        <span className="flex min-w-0 flex-col">
                            <span className="flex items-center gap-2 font-medium">
                                Watch
                                <span className="rounded bg-muted px-1.5 py-0.5 text-xs font-normal text-muted-foreground">
                                    Coming soon
                                </span>
                            </span>
                            <span className="text-muted-foreground">
                                Its commits, plus every commit mentioning @
                                {organization.handle}
                            </span>
                        </span>
                    </DropdownMenuCheckboxItem>
                    <DropdownMenuSeparator />
                    <DropdownMenuItem
                        variant="destructive"
                        className="pl-8"
                        onSelect={toggle}
                    >
                        Unfollow
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        );
    }

    return (
        <Button
            ref={buttonRef}
            variant={isFollowing ? 'outline' : 'default'}
            size={size}
            className={cn('group/follow', className)}
            aria-label={
                isFollowing
                    ? `Unfollow @${organization.handle}`
                    : `Follow @${organization.handle}`
            }
            onClick={() => {
                markToggled();
                toggle();
            }}
            onMouseLeave={onMouseLeave}
        >
            <FollowLabel
                isFollowing={isFollowing}
                isJustToggled={isJustToggled}
            />
        </Button>
    );
}
