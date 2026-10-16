import { Check, Copy } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';
import { cn } from '@/lib/utils';

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

    return (
        <div className="group/code relative my-2 rounded-md border bg-muted/60">
            {/* The server escapes the code while highlighting it. */}
            <pre
                ref={codeRef}
                className="overflow-x-auto px-3 py-2.5 font-mono text-[13px] leading-normal wrap-normal whitespace-pre"
                dangerouslySetInnerHTML={{ __html: html }}
            />
            <div className="absolute top-1.5 right-1.5 grid place-items-center *:[grid-area:1/1]">
                {language && (
                    <span
                        className={cn(
                            'rounded-sm bg-muted px-1.5 font-mono text-[11px] text-muted-foreground transition-opacity select-none group-hover/code:opacity-0 group-has-focus-visible/code:opacity-0 [@media(hover:none)]:opacity-0',
                            isCopied && 'opacity-0',
                        )}
                    >
                        {language}
                    </span>
                )}
                <button
                    type="button"
                    onClick={copyCode}
                    aria-label="Copy code"
                    title="Copy code"
                    className={cn(
                        'relative grid size-7 place-items-center rounded-md bg-muted text-muted-foreground opacity-0 transition group-hover/code:opacity-100 group-has-focus-visible/code:opacity-100 hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring [@media(hover:none)]:opacity-100',
                        isCopied && 'text-foreground opacity-100',
                    )}
                >
                    <Copy
                        className={cn(
                            'absolute size-3.5 transition-[opacity,scale,filter] duration-200 motion-reduce:transition-none',
                            isCopied && 'scale-50 opacity-0 blur-[2px]',
                        )}
                        aria-hidden
                    />
                    <Check
                        className={cn(
                            'absolute size-3.5 transition-[opacity,scale,filter] duration-200 motion-reduce:transition-none',
                            !isCopied && 'scale-50 opacity-0 blur-[2px]',
                        )}
                        aria-hidden
                    />
                </button>
                <span role="status" className="sr-only">
                    {isCopied ? 'Code copied to clipboard' : ''}
                </span>
            </div>
        </div>
    );
}
