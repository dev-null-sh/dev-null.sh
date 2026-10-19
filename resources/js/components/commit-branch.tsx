import { Link } from '@inertiajs/react';
import { GitBranch } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { VerifiedDomain } from '@/components/verified-domain';
import { branchColorStyle, branchTextColor } from '@/lib/branch-color';
import { cn } from '@/lib/utils';
import { show as branchShow } from '@/routes/branch';
import type { CommitBranchSummary } from '@/types/commit';

/**
 * The commit's branch, shown under the author like a git branch label.
 * Hover shows a mini branch page header (banner, name, verified via, stats).
 */
export function CommitBranch({
    branch,
    color,
    className,
}: {
    branch: CommitBranchSummary;
    color?: string | null;
    className?: string;
}) {
    const brandColor = color ?? branch.brand_color;
    const commitsLabel = `${branch.commits_count} ${branch.commits_count === 1 ? 'commit' : 'commits'}`;
    const authorsLabel = `${branch.authors_count} ${branch.authors_count === 1 ? 'author' : 'authors'}`;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={branchShow(branch.name)}
                    style={branchColorStyle(brandColor)}
                    className={cn(
                        'mt-1 inline-flex w-fit max-w-full items-center gap-1 rounded-md border border-primary/35 bg-primary-soft px-1.5 py-0.5 font-mono text-xs text-primary hover:opacity-90',
                        className,
                    )}
                    data-test="commit-branch"
                >
                    <GitBranch className="size-3 shrink-0" aria-hidden />
                    <span className="truncate">{branch.name}</span>
                </Link>
            </TooltipTrigger>
            <TooltipContent
                side="top"
                className="w-72 gap-0 overflow-hidden p-0 text-left"
            >
                {branch.og_image_url && (
                    <div className="aspect-[2.4/1] w-full overflow-hidden border-b bg-muted">
                        <img
                            src={branch.og_image_url}
                            alt=""
                            className="size-full object-cover"
                        />
                    </div>
                )}
                <div className="space-y-1.5 px-3 py-2.5">
                    <p
                        className="flex min-w-0 items-center gap-1.5 font-semibold"
                        style={
                            brandColor
                                ? { color: branchTextColor(brandColor) }
                                : undefined
                        }
                    >
                        <GitBranch
                            className="size-3.5 shrink-0"
                            aria-hidden
                        />
                        <span className="truncate font-mono text-[13px]">
                            {branch.name}
                        </span>
                    </p>
                    {branch.is_verified && branch.verified_domain && (
                        <VerifiedDomain
                            domain={branch.verified_domain}
                            className="text-[12px]"
                        />
                    )}
                    {!branch.is_verified && (
                        <p className="font-mono text-[12px] text-muted-foreground">
                            Unverified
                        </p>
                    )}
                    {branch.description && (
                        <p className="line-clamp-2 text-[12px] leading-snug text-muted-foreground">
                            {branch.description}
                        </p>
                    )}
                    <p className="font-mono text-[12px] text-muted-foreground">
                        {commitsLabel} · {authorsLabel}
                    </p>
                </div>
            </TooltipContent>
        </Tooltip>
    );
}
