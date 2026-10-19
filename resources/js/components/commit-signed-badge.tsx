import { ShieldCheck } from 'lucide-react';
import CommitSignatureController from '@/actions/App/Http/Controllers/CommitSignatureController';
import {
    Popover,
    PopoverContent,
    PopoverTrigger,
} from '@/components/ui/popover';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { Commit } from '@/types';

/**
 * The proof link sits in a popover that opens on click, because a tooltip closes before its link can be reached.
 */
export function CommitSignedBadge({
    commit,
    size = 'default',
}: {
    commit: Pick<Commit, 'id' | 'signed_at'>;
    size?: 'default' | 'small';
}) {
    if (!commit.signed_at) {
        return null;
    }

    return (
        <Popover>
            <Tooltip>
                <TooltipTrigger asChild>
                    <PopoverTrigger asChild>
                        <button
                            type="button"
                            className="inline-flex shrink-0 cursor-help items-center self-center text-emerald-600 dark:text-emerald-400"
                            aria-label="Signed with a passkey"
                            data-test="commit-signed"
                        >
                            <ShieldCheck
                                aria-hidden
                                className={
                                    size === 'default' ? 'size-4' : 'size-3.5'
                                }
                            />
                        </button>
                    </PopoverTrigger>
                </TooltipTrigger>
                <TooltipContent>Signed with a passkey</TooltipContent>
            </Tooltip>
            <PopoverContent
                side="bottom"
                className="w-72 p-3 text-sm"
                onOpenAutoFocus={(event) => event.preventDefault()}
            >
                <p>
                    The author signed this commit with a passkey when they
                    posted it,{' '}
                    <time
                        dateTime={commit.signed_at}
                        className="font-mono text-[13px]"
                    >
                        {new Date(commit.signed_at).toLocaleString()}
                    </time>
                    . A stolen session or GitHub account can't do that.
                </p>
                <a
                    href={CommitSignatureController.show(commit.id).url}
                    target="_blank"
                    rel="noopener"
                    className="mt-2 inline-block font-mono text-[13px] underline-offset-4 hover:underline"
                >
                    View the proof →
                </a>
            </PopoverContent>
        </Popover>
    );
}
