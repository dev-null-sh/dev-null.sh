import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';

export function CodeBlock({
    html,
    language,
}: {
    html: string;
    language: string | null;
}) {
    const codeRef = useRef<HTMLPreElement>(null);
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);

    useEffect(() => {
        if (!isCopied) {
            return;
        }

        const timeout = setTimeout(() => setIsCopied(false), 2000);

        return () => clearTimeout(timeout);
    }, [isCopied]);

    const copyCode = async () => {
        if (!codeRef.current) {
            return;
        }

        if (await copy(codeRef.current.textContent ?? '')) {
            setIsCopied(true);

            return;
        }

        setIsCopied(false);
        toast.error(
            <span className="font-mono text-[13px]">
                Could not copy code. Select it and copy manually.
            </span>,
        );
    };

    const Icon = isCopied ? Check : Copy;

    return (
        <div className="my-2 rounded-md border bg-muted/60">
            <div className="flex items-center justify-end gap-2 px-2 pt-1">
                {language && (
                    <span className="font-mono text-[11px] text-muted-foreground select-none">
                        {language}
                    </span>
                )}
                <button
                    type="button"
                    onClick={copyCode}
                    aria-label="Copy code"
                    title="Copy code"
                    className="flex min-h-7 min-w-18 items-center justify-center gap-1.5 rounded-sm px-1.5 font-mono text-[11px] text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring"
                >
                    <Icon className="size-3.5" aria-hidden />
                    {isCopied ? 'Copied' : 'Copy'}
                </button>
                <span role="status" className="sr-only">
                    {isCopied ? 'Code copied to clipboard' : ''}
                </span>
            </div>
            {/* The server escapes the code while highlighting it. */}
            <pre
                ref={codeRef}
                className="overflow-x-auto px-3 pt-1 pb-2.5 font-mono text-[13px] leading-normal wrap-normal whitespace-pre"
                dangerouslySetInnerHTML={{ __html: html }}
            />
        </div>
    );
}
