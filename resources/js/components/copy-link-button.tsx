import { useLang } from '@erag/lang-sync-inertia/react';
import { Check, LinkIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn, shortHash } from '@/lib/utils';
import type { Commit } from '@/types';

export function useCopyLink(commit: Commit) {
    const { __ } = useLang();
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);
    const hash = shortHash(commit.id);

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copyLink = async () => {
        const { url } = CommitController.show({ user: commit.user, commit });

        if (await copy(window.location.origin + url)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    {__('commits.copy_link.copied', { hash })}
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: {__('commits.copy_link.failed', { hash })}
            </span>,
        );
    };

    return { isCopied, copyLink };
}

export function CopyLinkButton({
    commit,
    keyShortcut,
    className,
}: {
    commit: Commit;
    keyShortcut?: string;
    className?: string;
}) {
    const { __ } = useLang();
    const { isCopied, copyLink } = useCopyLink(commit);
    const Icon = isCopied ? Check : LinkIcon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    onClick={copyLink}
                    aria-label={__('commits.copy_link.copy')}
                    aria-keyshortcuts={keyShortcut}
                    className={cn(
                        'flex items-center rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                        className,
                    )}
                    data-test="copy-link-button"
                >
                    <Icon className="size-4" />
                </button>
            </TooltipTrigger>
            <TooltipContent className="flex items-center gap-2">
                {__('commits.copy_link.copy')}
                {keyShortcut && (
                    <kbd className="rounded border border-b-2 bg-muted px-1.5 font-mono text-[11px] text-muted-foreground">
                        {keyShortcut}
                    </kbd>
                )}
            </TooltipContent>
        </Tooltip>
    );
}
