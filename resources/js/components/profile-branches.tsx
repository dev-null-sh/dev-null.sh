import { Link } from '@inertiajs/react';
import { BadgeCheck, GitBranch } from 'lucide-react';
import { EmptyFeed } from '@/components/commit-feed';
import { branchTextColor } from '@/lib/branch-color';
import { show as branchShow } from '@/routes/branch';

export type ProfileBranch = {
    name: string;
    role: 'maintainer' | 'contributor';
    description: string | null;
    is_verified: boolean;
    verified_domain: string | null;
    brand_color: string | null;
};

const roleLabel = {
    maintainer: 'Maintainer',
    contributor: 'Contributor',
} as const;

function faviconFor(domain: string): string {
    return `https://www.google.com/s2/favicons?domain=${encodeURIComponent(domain)}&sz=32`;
}

/**
 * Branches the profile user maintains or contributes to.
 */
export function ProfileBranches({
    branches,
    username,
    isCurrentUser,
}: {
    branches: ProfileBranch[];
    username: string;
    isCurrentUser: boolean;
}) {
    if (branches.length === 0) {
        return (
            <div className="px-6 py-16 text-center sm:px-12">
                <EmptyFeed command="fatal: no branches yet">
                    {isCurrentUser
                        ? 'Branches you maintain or contribute to show up here.'
                        : `@${username} isn't on any branch teams yet.`}
                </EmptyFeed>
            </div>
        );
    }

    return (
        <ul
            className="divide-y"
            aria-label="Branches"
            data-test="profile-branches"
        >
            {branches.map((branch) => {
                const nameColor = branch.brand_color
                    ? branchTextColor(branch.brand_color)
                    : undefined;

                return (
                    <li key={branch.name}>
                        <Link
                            href={branchShow(branch.name)}
                            className="flex gap-3 px-4 py-4 transition-colors hover:bg-accent/50 sm:px-6"
                        >
                            <span
                                className="mt-0.5 grid size-9 shrink-0 place-items-center rounded-full bg-muted"
                                style={
                                    nameColor
                                        ? {
                                            color: nameColor,
                                            backgroundColor: `color-mix(in oklab, ${nameColor} 12%, transparent)`,
                                        }
                                        : undefined
                                }
                            >
                                {branch.is_verified &&
                                    branch.verified_domain ? (
                                    <img
                                        src={faviconFor(branch.verified_domain)}
                                        alt=""
                                        width={18}
                                        height={18}
                                        className="size-[18px] rounded-sm"
                                    />
                                ) : (
                                    <GitBranch
                                        className="size-4"
                                        aria-hidden
                                    />
                                )}
                            </span>
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                                    <p className="flex min-w-0 items-center gap-1.5">
                                        <span
                                            className="truncate font-mono text-[15px] font-semibold"
                                            style={
                                                nameColor
                                                    ? { color: nameColor }
                                                    : undefined
                                            }
                                        >
                                            {branch.name}
                                        </span>
                                        {branch.is_verified && (
                                            <BadgeCheck
                                                className="size-3.5 shrink-0 fill-amber-400/25 text-amber-500 dark:fill-amber-400/20 dark:text-amber-400"
                                                aria-label={
                                                    branch.verified_domain
                                                        ? `Verified via ${branch.verified_domain}`
                                                        : 'Verified'
                                                }
                                            />
                                        )}
                                    </p>
                                    <span className="shrink-0 rounded-md bg-muted px-1.5 py-0.5 text-xs font-medium text-muted-foreground">
                                        {roleLabel[branch.role]}
                                    </span>
                                </div>
                                {branch.description && (
                                    <p className="line-clamp-2 text-sm leading-relaxed text-pretty text-muted-foreground">
                                        {branch.description}
                                    </p>
                                )}
                                {branch.is_verified &&
                                    branch.verified_domain && (
                                        <p className="flex min-w-0 items-center gap-1.5 font-mono text-[12px] text-muted-foreground">
                                            <img
                                                src={faviconFor(
                                                    branch.verified_domain,
                                                )}
                                                alt=""
                                                width={12}
                                                height={12}
                                                className="size-3 shrink-0 rounded-sm"
                                            />
                                            <span className="truncate">
                                                {branch.verified_domain}
                                            </span>
                                        </p>
                                    )}
                            </div>
                        </Link>
                    </li>
                );
            })}
        </ul>
    );
}
