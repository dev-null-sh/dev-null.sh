import { Check, LinkIcon } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn, shortHash } from '@/lib/utils';
import type { Commit } from '@/types';

export function CopyLinkButton({
    commit,
    keyShortcut,
    className,
}: {
    commit: Commit;
    keyShortcut?: string;
    className?: string;
}) {
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

    const Icon = isCopied ? Check : LinkIcon;

    return (
        <button
            type="button"
            onClick={copyLink}
            aria-label="Copy link"
            aria-keyshortcuts={keyShortcut}
            title={keyShortcut ? `Copy link (${keyShortcut})` : 'Copy link'}
            className={cn(
                'flex items-center rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground',
                className,
            )}
            data-test="copy-link-button"
        >
            <Icon className="size-4" />
        </button>
    );
}
