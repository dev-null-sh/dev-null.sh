import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { attachmentFromResult } from '@/lib/gif-providers';
import type {
    GifAttachment,
    GifProvider,
    GifResult,
} from '@/lib/gif-providers';
import { GifRateLimited } from '@/lib/gif-providers';

type Status = 'loading' | 'ready' | 'error' | 'rate-limited';

export function GifPickerDialog({
    provider,
    apiKey,
    open,
    onOpenChange,
    onPick,
}: {
    provider: GifProvider;
    apiKey: string;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onPick: (gif: GifAttachment) => void;
}) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<GifResult[]>([]);
    const [nextCursor, setNextCursor] = useState<string | null>(null);
    const [status, setStatus] = useState<Status>('loading');
    const [isLoadingMore, setIsLoadingMore] = useState(false);
    const [reloads, setReloads] = useState(0);
    const generation = useRef(0);
    const queryRef = useRef(query);
    const loadMoreController = useRef<AbortController | null>(null);

    useEffect(() => {
        const currentGeneration = ++generation.current;
        queryRef.current = query;

        if (!open) {
            return;
        }

        setNextCursor(null);

        const controller = new AbortController();
        const timeout = setTimeout(
            () => {
                setIsLoadingMore(false);
                setStatus('loading');
                const trimmed = query.trim();
                const request =
                    trimmed === ''
                        ? provider.trending(apiKey, {
                              cursor: null,
                              signal: controller.signal,
                          })
                        : provider.search(apiKey, trimmed, {
                              cursor: null,
                              signal: controller.signal,
                          });

                request
                    .then((page) => {
                        if (
                            controller.signal.aborted ||
                            currentGeneration !== generation.current
                        ) {
                            return;
                        }

                        setResults(page.results);
                        setNextCursor(page.nextCursor);
                        setStatus('ready');
                    })
                    .catch((error: unknown) => {
                        if (controller.signal.aborted) {
                            return;
                        }

                        setStatus(
                            error instanceof GifRateLimited
                                ? 'rate-limited'
                                : 'error',
                        );
                    });
            },
            query === '' ? 0 : 300,
        );

        return () => {
            clearTimeout(timeout);
            controller.abort();
            loadMoreController.current?.abort();
        };
    }, [open, query, apiKey, reloads, provider]);

    const loadMore = () => {
        const currentGeneration = generation.current;
        const requestedQuery = query;
        const controller = new AbortController();
        loadMoreController.current = controller;
        setIsLoadingMore(true);
        const trimmed = query.trim();
        const request =
            trimmed === ''
                ? provider.trending(apiKey, {
                      cursor: nextCursor,
                      signal: controller.signal,
                  })
                : provider.search(apiKey, trimmed, {
                      cursor: nextCursor,
                      signal: controller.signal,
                  });

        request
            .then((page) => {
                if (
                    controller.signal.aborted ||
                    currentGeneration !== generation.current ||
                    requestedQuery !== queryRef.current
                ) {
                    return;
                }

                setResults((current) => [...current, ...page.results]);
                setNextCursor(page.nextCursor);
            })
            .catch(() => undefined)
            .finally(() => {
                if (
                    currentGeneration === generation.current &&
                    requestedQuery === queryRef.current
                ) {
                    setIsLoadingMore(false);
                }
            });
    };

    const pick = (result: GifResult) => {
        onPick(attachmentFromResult(provider.id, result));
        onOpenChange(false);
        setQuery('');
    };

    const loadMoreButton = nextCursor !== null && (
        <Button
            type="button"
            variant="secondary"
            size="sm"
            disabled={isLoadingMore}
            onClick={loadMore}
            className="mx-auto"
        >
            {isLoadingMore ? 'Loading…' : 'Load more'}
        </Button>
    );

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[80vh] overflow-y-auto sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Attach a GIF</DialogTitle>
                    <DialogDescription className="sr-only">
                        Search {provider.label} and pick a GIF to attach.
                    </DialogDescription>
                </DialogHeader>
                <input
                    autoFocus
                    value={query}
                    onChange={(event) => setQuery(event.target.value)}
                    placeholder={provider.searchPlaceholder}
                    aria-label={provider.searchPlaceholder}
                    autoComplete="off"
                    spellCheck={false}
                    className="h-9 w-full min-w-0 rounded-md border bg-card px-3 text-base outline-none placeholder:text-muted-foreground focus-visible:border-primary md:text-sm"
                    data-test="gif-search-input"
                />
                {status === 'loading' && (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        Loading…
                    </p>
                )}
                {status === 'rate-limited' && (
                    <p className="py-8 text-center text-sm text-muted-foreground">
                        You’ve hit your {provider.label} key’s search rate
                        limit. Try again shortly.
                    </p>
                )}
                {status === 'error' && (
                    <div className="py-8 text-center">
                        <p className="text-sm text-muted-foreground">
                            {provider.label} did not answer.
                        </p>
                        <Button
                            type="button"
                            variant="secondary"
                            size="sm"
                            className="mt-2"
                            onClick={() => setReloads((count) => count + 1)}
                        >
                            Retry
                        </Button>
                    </div>
                )}
                {status === 'ready' && results.length === 0 && (
                    <>
                        <p className="py-8 text-center text-sm text-muted-foreground">
                            No GIFs matched. /dev/null remains empty.
                        </p>
                        {loadMoreButton}
                    </>
                )}
                {status === 'ready' && results.length > 0 && (
                    <>
                        <ul
                            className="grid grid-cols-3 gap-1"
                            data-test="gif-results"
                        >
                            {results.map((result) =>
                                result.previewUrl === null ? null : (
                                    <li key={result.id}>
                                        <button
                                            type="button"
                                            onClick={() => pick(result)}
                                            className="block w-full overflow-hidden rounded-md border focus-visible:ring-2 focus-visible:ring-ring"
                                            aria-label={
                                                result.title === null
                                                    ? 'Attach GIF'
                                                    : `Attach: ${result.title}`
                                            }
                                        >
                                            <img
                                                src={result.previewUrl}
                                                alt={result.title ?? 'GIF'}
                                                loading="lazy"
                                                referrerPolicy="no-referrer"
                                                className="h-24 w-full bg-muted object-cover"
                                            />
                                        </button>
                                    </li>
                                ),
                            )}
                        </ul>
                        {loadMoreButton}
                    </>
                )}
                <div className="flex justify-center border-t pt-3">
                    <a
                        href={provider.attribution.href}
                        target="_blank"
                        rel="noopener noreferrer"
                    >
                        <span className="font-mono text-[11px] font-bold tracking-wide text-muted-foreground">
                            {provider.attribution.label}
                        </span>
                    </a>
                </div>
            </DialogContent>
        </Dialog>
    );
}
