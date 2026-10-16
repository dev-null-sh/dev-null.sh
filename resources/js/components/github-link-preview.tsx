import { Link } from '@inertiajs/react';
import {
    BookMarked,
    CircleDot,
    FileCode,
    GitCommitHorizontal,
    GitMerge,
    GitPullRequest,
    Star,
    Tag,
} from 'lucide-react';
import type { MouseEvent } from 'react';
import { cn } from '@/lib/utils';
import { show } from '@/routes/user';
import type { GitHubPreview } from '@/types';

const icons = {
    repository: BookMarked,
    issue: CircleDot,
    pull_request: GitPullRequest,
    release: Tag,
    commit: GitCommitHorizontal,
    code: FileCode,
};

const compact = new Intl.NumberFormat('en', { notation: 'compact' });
const exact = new Intl.NumberFormat('en');

function stopPropagation(event: MouseEvent) {
    event.stopPropagation();
}

// The whole card links to GitHub through a link stretched over it, so that the author can link to their profile on
// devnull and the code can be selected and scrolled without nesting one link inside another.
export function GitHubLinkPreview({
    url,
    title,
    github,
}: {
    url: string;
    title: string | null;
    github: GitHubPreview;
}) {
    const Icon = github.state === 'merged' ? GitMerge : icons[github.type];

    return (
        <div
            className="relative mt-3 overflow-hidden rounded-lg border transition-colors hover:bg-accent has-[a:focus-visible]:bg-accent"
            data-test="github-link-preview"
        >
            <a
                href={url}
                target="_blank"
                rel="noopener noreferrer nofollow ugc"
                onClick={stopPropagation}
                aria-label={[github.label, title].filter(Boolean).join(': ')}
                className="absolute inset-0 outline-none"
            />

            <div className="flex items-center gap-3 px-3 py-2">
                <Icon className="size-4 shrink-0 text-muted-foreground" />

                <div className="min-w-0 flex-1">
                    <div className="flex min-w-0 items-center gap-1.5 font-mono text-[13px] text-muted-foreground">
                        <span className="truncate">{github.label}</span>
                        {github.author && (
                            <span className="shrink-0">
                                · by{' '}
                                {github.author_on_devnull ? (
                                    <Link
                                        href={show(github.author)}
                                        onClick={stopPropagation}
                                        className="relative z-10 text-primary hover:underline"
                                    >
                                        @{github.author}
                                    </Link>
                                ) : (
                                    `@${github.author}`
                                )}
                            </span>
                        )}
                    </div>
                    {title && (
                        <div
                            className={cn(
                                'truncate text-[15px] leading-snug font-medium',
                                github.type === 'code' &&
                                    'font-mono text-[13px]',
                            )}
                        >
                            {title}
                        </div>
                    )}
                </div>

                {github.stars !== null && (
                    <span className="flex shrink-0 items-center gap-1 font-mono text-[13px] text-muted-foreground">
                        <Star className="size-3.5" />
                        {compact.format(github.stars)}
                        {github.language && ` · ${github.language}`}
                    </span>
                )}

                {(github.state || github.additions !== undefined) && (
                    <div className="shrink-0 text-right font-mono text-[13px] text-muted-foreground">
                        {github.state && <div>{github.state}</div>}
                        {github.additions !== undefined &&
                            github.deletions !== undefined && (
                                <div
                                    className="relative z-10"
                                    title={
                                        github.files === undefined
                                            ? undefined
                                            : `${exact.format(github.files)} ${github.files === 1 ? 'file' : 'files'} changed`
                                    }
                                >
                                    <span className="hl-addition">
                                        +{exact.format(github.additions)}
                                    </span>{' '}
                                    <span className="hl-deletion">
                                        −{exact.format(github.deletions)}
                                    </span>
                                </div>
                            )}
                    </div>
                )}
            </div>

            {github.lines && github.html && (
                <div className="relative z-10 flex border-t bg-muted/60 font-mono text-[13px] leading-normal [tab-size:4]">
                    <pre
                        aria-hidden
                        className="px-3 py-2.5 text-right text-muted-foreground select-none"
                    >
                        {github.lines
                            .map((_, index) => (github.start ?? 1) + index)
                            .join('\n')}
                    </pre>
                    {/* The server escapes the code while highlighting it. */}
                    <pre
                        className="min-w-0 flex-1 overflow-x-auto py-2.5 pr-3 wrap-normal whitespace-pre"
                        dangerouslySetInnerHTML={{ __html: github.html }}
                    />
                </div>
            )}
        </div>
    );
}
