import { useHttp, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitTranslationController from '@/actions/App/Http/Controllers/CommitTranslationController';
import { useCommitNotFound } from '@/hooks/use-deleted-resources';
import { signIn } from '@/lib/auth';
import { coalesce, commitChannel, listen } from '@/lib/realtime';
import type { Commit, CommitTranslation } from '@/types';

export function useCommitTranslation(commit: Commit) {
    const { auth } = usePage().props;
    const commitNotFound = useCommitNotFound();
    const bodyRef = useRef<HTMLDivElement>(null);
    const [bodyHeight, setBodyHeight] = useState<number>();
    const [generated, setGenerated] = useState<{
        body: string;
        translation: CommitTranslation | null;
    } | null>(null);
    const [display, setDisplay] = useState<'original' | 'translated' | null>(
        null,
    );
    const translation =
        generated?.body === commit.body &&
        generated.translation?.language === commit.translation?.language
            ? generated.translation
            : commit.translation;
    const request = useHttp<
        { language: string },
        { translation: CommitTranslation | null }
    >({ language: translation?.language ?? 'en' });
    const translated =
        (display === 'translated' ||
            (display === null && auth.automaticTranslation)) &&
        translation?.translated;
    const language = translation?.language ?? null;
    const isApproved = commit.status === 'approved';
    const isTranslated = Boolean(translation?.translated);

    // A translation finished for someone else is read again in this reader's language; showing the original stays.
    useEffect(() => {
        if (!isApproved || language === null) {
            return;
        }

        const controller = new AbortController();
        const body = commit.body;
        const read = coalesce(async () => {
            const response = await fetch(
                CommitTranslationController.show.url(commit.id, {
                    query: { language },
                }),
                {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store',
                    signal: controller.signal,
                },
            );

            if (response.ok) {
                const fresh = (await response.json()) as {
                    translation: CommitTranslation | null;
                };

                setGenerated({ body, translation: fresh.translation });
            }
        }, 500);
        // A translation finished while not listening is only learnt by reading again, which joining does while
        // none is shown yet.
        const stop = listen(
            commitChannel(commit.id),
            { translation: read.request },
            isTranslated ? undefined : read.request,
        );

        return () => {
            stop();
            read.cancel();
            controller.abort();
        };
    }, [commit.id, commit.body, isApproved, language, isTranslated]);

    const translate = async () => {
        setBodyHeight(bodyRef.current?.getBoundingClientRect().height);

        if (translation?.translated) {
            setDisplay('translated');
            return;
        }

        if (!auth.user) {
            signIn();
            return;
        }

        request.transform(() => ({ language: translation?.language ?? 'en' }));

        let isNotFound = false;
        let failureMessage = 'Could not translate this commit. Try again.';

        try {
            const response = await request.submit(
                CommitTranslationController.store(commit.id),
                {
                    onHttpException: (response) => {
                        isNotFound = commitNotFound(commit.id, response);

                        if (
                            response.status === 503 &&
                            response.headers['content-type']?.includes(
                                'application/json',
                            )
                        ) {
                            const { message } = JSON.parse(response.data) as {
                                message: string;
                            };
                            failureMessage = message;
                        }
                    },
                },
            );
            setGenerated({
                body: commit.body,
                translation: response.translation,
            });
            setDisplay('translated');
        } catch {
            if (!isNotFound) {
                toast.error(failureMessage);
            }
        }
    };

    return {
        bodyRef,
        bodyStyle: { minHeight: bodyHeight },
        segments: translated ? translated.body_segments : commit.body_segments,
        previewSegments: translated
            ? translated.preview_segments
            : commit.preview_segments,
        notice: translation?.available ? (
            <div className="flex min-h-5 min-w-0 items-center gap-1 text-xs text-muted-foreground">
                {translated && (
                    <>
                        <span
                            className="truncate"
                            title={`Translated from ${translation.source_name}`}
                        >
                            Translated from {translation.source_name}
                        </span>
                        <span aria-hidden>·</span>
                        <button
                            type="button"
                            onClick={() => {
                                setBodyHeight(
                                    bodyRef.current?.getBoundingClientRect()
                                        .height,
                                );
                                setDisplay('original');
                            }}
                            className="shrink-0 text-primary hover:underline"
                        >
                            Show original
                        </button>
                    </>
                )}
            </div>
        ) : null,
        button: translation?.available ? (
            <button
                type="button"
                onClick={() => void translate()}
                disabled={request.processing || Boolean(translated)}
                aria-label={
                    request.processing
                        ? 'Translating commit'
                        : 'Translate commit'
                }
                aria-busy={request.processing}
                className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground disabled:opacity-50 sm:min-w-24"
                data-test="translate-commit"
            >
                <Languages className="size-4" aria-hidden />
                <span className="sr-only sm:not-sr-only">Translate</span>
            </button>
        ) : null,
    };
}
