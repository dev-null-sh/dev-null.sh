import { Check, Copy, WrapText } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';
import { withCertificateHashes } from '@/lib/certificate-hashes';
import { cn } from '@/lib/utils';

export function CodeBlock({
    html,
    fullHtml,
    language,
    filename,
    isCopyable = true,
}: {
    html: string;
    fullHtml?: string;
    language: string | null;
    filename: string | null;
    isCopyable?: boolean;
}) {
    const codeRef = useRef<HTMLPreElement>(null);
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);
    const [isWrapped, setIsWrapped] = useState(false);

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

        let code = codeRef.current.textContent ?? '';

        if (fullHtml !== undefined) {
            // The server escapes the code while highlighting it, and a template runs nothing.
            const template = document.createElement('template');
            template.innerHTML = fullHtml;
            code = template.content.textContent ?? '';
        }

        if (await copy(code)) {
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

    return (
        <div className="my-2 rounded-md border bg-muted/60">
            {(language || isCopyable) && (
                <div className="flex h-8 items-center justify-between gap-2 border-b pr-1 pl-3">
                    <span
                        className="min-w-0 truncate font-mono text-[11px] text-muted-foreground select-none"
                        title={filename ?? undefined}
                    >
                        {filename ?? language}
                    </span>
                    {isCopyable && (
                        <div className="flex shrink-0 items-center gap-0.5">
                            <button
                                type="button"
                                onClick={() => setIsWrapped(!isWrapped)}
                                aria-label="Wrap lines"
                                aria-pressed={isWrapped}
                                title="Wrap lines"
                                className={cn(
                                    'grid size-6 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    isWrapped && 'bg-muted text-foreground',
                                )}
                            >
                                <WrapText className="size-3.5" aria-hidden />
                            </button>
                            <button
                                type="button"
                                onClick={copyCode}
                                aria-label="Copy code"
                                title="Copy code"
                                className={cn(
                                    'relative grid size-6 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                    isCopied && 'text-foreground',
                                )}
                            >
                                <Copy
                                    className={cn(
                                        'absolute size-3.5 transition-[opacity,scale,filter] duration-200 motion-reduce:transition-none',
                                        isCopied &&
                                            'scale-50 opacity-0 blur-[2px]',
                                    )}
                                    aria-hidden
                                />
                                <Check
                                    className={cn(
                                        'absolute size-3.5 transition-[opacity,scale,filter] duration-200 motion-reduce:transition-none',
                                        !isCopied &&
                                            'scale-50 opacity-0 blur-[2px]',
                                    )}
                                    aria-hidden
                                />
                            </button>
                            <span role="status" className="sr-only">
                                {isCopied ? 'Code copied to clipboard' : ''}
                            </span>
                        </div>
                    )}
                </div>
            )}
            {/* The server escapes the code while highlighting it. */}
            <pre
                ref={codeRef}
                tabIndex={isCopyable ? undefined : -1}
                className={cn(
                    'px-3 py-2.5 font-mono text-[13px] leading-normal',
                    isWrapped
                        ? 'overflow-x-hidden wrap-anywhere whitespace-pre-wrap'
                        : 'overflow-x-auto wrap-normal whitespace-pre',
                )}
                dangerouslySetInnerHTML={{
                    __html: withCertificateHashes(html),
                }}
            />
        </div>
    );
}
