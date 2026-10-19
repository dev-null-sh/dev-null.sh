import { Link } from '@inertiajs/react';
import { BadgeCheck, GitBranch } from 'lucide-react';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { branchColorStyle } from '@/lib/branch-color';
import { cn } from '@/lib/utils';
import { show as tagShow } from '@/routes/tag';
import type { CommitBranchSummary } from '@/types/commit';

/**
 * The commit's branch, shown under the author like a git branch label.
 * Verified branches use the favicon's primary colour.
 */
export function CommitBranch({
    tag,
    color,
    branch,
    className,
}: {
    tag: string;
    color?: string | null;
    branch?: CommitBranchSummary | null;
    className?: string;
}) {
    const brandColor = color ?? branch?.brand_color;
    const commits = branch?.commits_count;
    const verified = branch?.is_verified ?? false;

    const commitLabel =
        commits === undefined
            ? null
            : `${commits} ${commits === 1 ? 'commit' : 'commits'}`;
    const label =
        commitLabel === null
            ? tag
            : verified
                ? commitLabel
                : `${commitLabel} · unverified`;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={tagShow(tag)}
                    style={branchColorStyle(brandColor)}
                    className={cn(
                        'mt-1 inline-flex w-fit max-w-full items-center gap-1 rounded-md border border-primary/35 bg-primary-soft px-1.5 py-0.5 font-mono text-xs text-primary hover:opacity-90',
                        className,
                    )}
                    data-test="commit-branch"
                >
                    <GitBranch className="size-3 shrink-0" aria-hidden />
                    <span className="truncate">{tag}</span>
                </Link>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-1.5 font-mono text-[12px]">
                {verified && (
                    <BadgeCheck
                        className="size-3.5 shrink-0 fill-amber-400/25 text-amber-500 dark:text-amber-400"
                        aria-hidden
                    />
                )}
                <span>{label}</span>
            </TooltipContent>
        </Tooltip>
    );
}
