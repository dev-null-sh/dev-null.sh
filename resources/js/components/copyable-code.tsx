import { Check, Copy } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';

export function CopyableCode({
    code,
    label,
    language,
}: {
    code: string;
    label: string;
    language?: string;
}) {
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
        if (await copy(code)) {
            setIsCopied(true);
            toast(
                <span className="font-mono text-[13px]">Copied {label}</span>,
            );

            return;
        }

        toast.error(
            <span className="font-mono text-[13px]">
                fatal: could not copy {label}
            </span>,
        );
    };

    const Icon = isCopied ? Check : Copy;

    const copyButton = (
        <button
            type="button"
            onClick={copyCode}
            aria-label={`Copy ${label}`}
            title={`Copy ${label}`}
            className="flex items-center rounded-md p-1 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
        >
            <Icon className="size-4" />
        </button>
    );

    const pre = (
        <pre className="min-w-0 flex-1 overflow-x-auto px-3 py-2.5 font-mono text-[13px] leading-normal wrap-normal whitespace-pre">
            <code>{code}</code>
        </pre>
    );

    if (!code.includes('\n')) {
        return (
            <div className="flex items-start rounded-md border bg-muted/60">
                {pre}
                <div className="shrink-0 p-1.5">{copyButton}</div>
            </div>
        );
    }

    return (
        <div className="rounded-md border bg-muted/60">
            <div className="flex items-center justify-between border-b py-1 pr-1.5 pl-3">
                <span className="font-mono text-[11px] text-muted-foreground select-none">
                    {language}
                </span>
                {copyButton}
            </div>
            {pre}
        </div>
    );
}
