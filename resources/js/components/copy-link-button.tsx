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
        const { url } = CommitController.show({ user: commit.author, commit });

        if (await copy(window.location.origin + url)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">
                    Copied link to {hash}
                </span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy link to {hash}
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
    const { isCopied, copyLink } = useCopyLink(commit);
    const Icon = isCopied ? Check : LinkIcon;

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <button
                    type="button"
                    onClick={copyLink}
                    aria-label="Copy link"
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
                Copy link
                {keyShortcut && (
                    <kbd className="rounded border border-b-2 bg-muted px-1.5 font-mono text-[11px] text-muted-foreground">
                        {keyShortcut}
                    </kbd>
                )}
            </TooltipContent>
        </Tooltip>
    );
}
