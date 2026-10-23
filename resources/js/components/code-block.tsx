import { Check, Copy, Play, Square, WrapText } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import { useClipboard } from '@/hooks/use-clipboard';
import { withCertificateHashes } from '@/lib/certificate-hashes';
import { cn } from '@/lib/utils';

export function CodeBlock({
    html,
    language,
    isCopyable = true,
    isRunnable = true,
}: {
    html: string;
    language: string | null;
    isCopyable?: boolean;
    isRunnable?: boolean;
}) {
    const codeRef = useRef<HTMLPreElement>(null);
    const [, copy] = useClipboard();
    const [isCopied, setIsCopied] = useState(false);
    const [isWrapped, setIsWrapped] = useState(false);
    const [output, setOutput] = useState<string[] | null>(null);
    const [ending, setEnding] = useState<string | null>(null);
    const running = useRef<AbortController | null>(null);
    const [isRunning, setIsRunning] = useState(false);
    const canRun =
        isCopyable && isRunnable && language?.toLowerCase() === 'php';

    useEffect(() => () => running.current?.abort(), []);

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

    // PHP only loads once someone runs a block, and runs in their browser: nothing is sent anywhere.
    const runCode = async () => {
        if (running.current) {
            running.current.abort();

            return;
        }

        const controller = new AbortController();
        // A run again keeps the last output until it prints its own, so the panel doesn't collapse and grow back.
        let isFirstLine = true;

        running.current = controller;
        setIsRunning(true);
        setOutput((current) => current ?? []);
        setEnding(null);

        try {
            const php = await import('@/lib/php');
            const result = await php.run(
                codeRef.current?.textContent ?? '',
                (line) => {
                    if (isFirstLine) {
                        isFirstLine = false;
                        setOutput([line]);
                    } else {
                        setOutput((current) => [...(current ?? []), line]);
                    }
                },
                controller.signal,
            );

            if (isFirstLine) {
                setOutput([]);
            }

            setEnding(php.ending(result));
        } catch {
            setEnding(
                'PHP could not start. Check your connection and try again.',
            );
        } finally {
            running.current = null;
            setIsRunning(false);
        }
    };

    return (
        <div className="my-2 rounded-md border bg-muted/60">
            {(language || isCopyable) && (
                <div className="flex h-8 items-center justify-between gap-2 border-b pr-1 pl-3">
                    <span className="min-w-0 truncate font-mono text-[11px] text-muted-foreground select-none">
                        {language}
                    </span>
                    {isCopyable && (
                        <div className="flex shrink-0 items-center gap-0.5">
                            {canRun && (
                                <button
                                    type="button"
                                    onClick={runCode}
                                    aria-label={
                                        isRunning ? 'Stop running' : 'Run code'
                                    }
                                    title={
                                        isRunning
                                            ? 'Stop running'
                                            : 'Run in your browser'
                                    }
                                    className={cn(
                                        'grid size-6 place-items-center rounded-md text-muted-foreground transition-colors hover:bg-muted hover:text-foreground focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ring',
                                        isRunning && 'text-foreground',
                                    )}
                                >
                                    {isRunning ? (
                                        <Square
                                            className="size-3"
                                            aria-hidden
                                        />
                                    ) : (
                                        <Play
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    )}
                                </button>
                            )}
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
            {output !== null && (
                // A click on the output is for reading it, not for opening the commit around it.
                <div
                    onClick={(event) => event.stopPropagation()}
                    className="border-t px-3 py-2 font-mono text-[12px] leading-normal"
                >
                    {output.length > 0 && (
                        <div className="mb-1.5 max-h-64 overflow-y-auto wrap-anywhere whitespace-pre-wrap">
                            {output.map((line, index) => (
                                <div key={index}>{line || ' '}</div>
                            ))}
                        </div>
                    )}
                    <p
                        role="status"
                        className="text-[11px] text-muted-foreground"
                    >
                        {ending ?? 'Running…'}
                    </p>
                </div>
            )}
        </div>
    );
}
