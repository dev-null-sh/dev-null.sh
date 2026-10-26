import { Ellipsis, Flag, LinkIcon } from 'lucide-react';
import { useState } from 'react';
import {
    CommitReportButton,
    CommitReportDialog,
    useCanReport,
} from '@/components/commit-report-button';
import { CopyLinkButton, useCopyLink } from '@/components/copy-link-button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

export function CommitSecondaryActions({
    commit,
    keyShortcut,
    isReply = false,
}: {
    commit: Commit;
    keyShortcut?: string;
    isReply?: boolean;
}) {
    return (
        <div className="ml-auto flex items-center gap-0.5">
            <div
                className={cn(
                    'hidden items-center gap-0.5 transition-opacity md:flex md:opacity-0 md:focus-within:opacity-100 [@media(hover:none)]:opacity-100',
                    isReply
                        ? 'md:reply-hover:opacity-100'
                        : 'md:commit-hover:opacity-100 md:commit-focus-within:opacity-100',
                )}
            >
                <CopyLinkButton commit={commit} keyShortcut={keyShortcut} />
                <CommitReportButton commit={commit} />
            </div>
            <CommitActionsMenu commit={commit} />
        </div>
    );
}

function CommitActionsMenu({ commit }: { commit: Commit }) {
    const [isReporting, setIsReporting] = useState(false);
    const { copyLink } = useCopyLink(commit);
    const canReport = useCanReport(commit);

    return (
        <div className="md:hidden">
            <DropdownMenu modal={false}>
                <DropdownMenuTrigger asChild>
                    <button
                        type="button"
                        aria-label="More actions"
                        className="rounded-md p-1.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none data-[state=open]:bg-accent data-[state=open]:text-foreground"
                        data-test="commit-actions-menu"
                    >
                        <Ellipsis aria-hidden className="size-4" />
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-48">
                    <DropdownMenuItem onSelect={copyLink}>
                        <LinkIcon />
                        Copy link
                    </DropdownMenuItem>
                    {canReport && (
                        <DropdownMenuItem onSelect={() => setIsReporting(true)}>
                            <Flag />
                            Report commit
                        </DropdownMenuItem>
                    )}
                </DropdownMenuContent>
            </DropdownMenu>
            {canReport && (
                <CommitReportDialog
                    commit={commit}
                    open={isReporting}
                    onOpenChange={setIsReporting}
                />
            )}
        </div>
    );
}
