import { GitPullRequest } from 'lucide-react';

export type PullRequests = { count: number; url: string };

export function ProfilePullRequests({
    pullRequests,
}: {
    pullRequests?: PullRequests | null;
}) {
    if (!pullRequests || pullRequests.count <= 0) {
        return null;
    }

    return (
        <div
            className="mt-3 flex h-6 items-center text-sm text-muted-foreground"
            data-test="profile-pull-requests"
        >
            <a
                href={pullRequests.url}
                target="_blank"
                rel="noopener noreferrer"
                title={`${pullRequests.count.toLocaleString('en-US')} public pull requests authored by this developer and merged on GitHub. Updated every 15 minutes.`}
                className="inline-flex items-center gap-1.5 rounded-sm hover:text-primary focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
            >
                <GitPullRequest
                    aria-hidden="true"
                    className="size-4 shrink-0"
                />
                <span className="min-w-[2ch] shrink-0 text-right font-mono text-[13px] font-medium text-foreground tabular-nums">
                    {pullRequests.count.toLocaleString('en-US')}
                </span>
                <span className="whitespace-nowrap">
                    merged {pullRequests.count === 1 ? 'PR' : 'PRs'}{' '}
                    <span className="text-xs text-muted-foreground">
                        on GitHub
                    </span>
                </span>
            </a>
        </div>
    );
}
