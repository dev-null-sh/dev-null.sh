import type { PendingVisit } from '@inertiajs/core';
import { router } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitPhotoController from '@/actions/App/Http/Controllers/CommitPhotoController';
import type { Commit } from '@/types';

export type CommitPhoto = {
    commit: Commit;
    index: number;
    backgroundUrl?: string;
};

type PhotoHistory = { sessionId: string; entryId: string };
const documentPhotoSessions = new Set<string>();
let photoSessionId: string | undefined;

export function trackCommitPhotoPage(): VoidFunction {
    let backgroundUrl: string | undefined;
    let component: string | undefined;
    let version: string | null | undefined;
    let pending:
        | {
              visit: PendingVisit;
              history?: PhotoHistory;
              url: string;
              component?: string;
              backgroundUrl?: string;
          }
        | undefined;
    let restoration = Promise.resolve();
    // The original method is called with its History receiver below.
    // eslint-disable-next-line typescript/unbound-method
    const replaceState = window.history.replaceState;
    const preservePhotoHistory: History['replaceState'] = (
        data,
        unused,
        url,
    ) => {
        // Inertia replaces history state for scrolling, polling, and remembered state.
        const photoHistory =
            data?.commitPhotoHistory !== undefined
                ? data.commitPhotoHistory
                : window.history.state?.commitPhotoHistory;
        replaceState.call(
            window.history,
            data?.page && photoHistory !== undefined
                ? { ...data, commitPhotoHistory: photoHistory }
                : data,
            unused,
            url,
        );
    };
    window.history.replaceState = preservePhotoHistory;

    const onPopState = (event: PopStateEvent) => {
        const target = event.state?.commitPhotoHistory as
            | PhotoHistory
            | undefined;
        if (!target || target.sessionId !== photoSessionId) {
            return;
        }

        // Native restoration remounts the page, losing replies and measured offscreen card heights.
        event.stopImmediatePropagation();
        router.cancelAll({ prefetch: false });
        const url = window.location.href;
        restoration = restoration
            .then(async () => {
                if (
                    window.history.state?.commitPhotoHistory?.entryId !==
                    target.entryId
                ) {
                    return;
                }
                const page = await router.decryptHistory();
                if (
                    window.history.state?.commitPhotoHistory?.entryId !==
                    target.entryId
                ) {
                    await router.decryptHistory();
                    return;
                }
                if (
                    page.version !== version ||
                    target.sessionId !== photoSessionId
                ) {
                    photoSessionId = undefined;
                    window.dispatchEvent(event);
                    return;
                }
                const photo = page.props.photo as CommitPhoto | undefined;
                router.replace({
                    url,
                    props: (props) => ({ ...props, photo }),
                    preserveScroll: true,
                    preserveState: true,
                    onFinish: () => {
                        backgroundUrl = photo?.backgroundUrl;
                    },
                });
            })
            .catch(() => {
                if (
                    window.history.state?.commitPhotoHistory?.entryId ===
                    target.entryId
                ) {
                    photoSessionId = undefined;
                    window.dispatchEvent(event);
                }
            });
    };
    window.addEventListener('popstate', onPopState, true);

    const stopNavigation = router.on('navigate', ({ detail: { page } }) => {
        const sessionId = window.history.state?.commitPhotoHistory?.sessionId;
        photoSessionId = documentPhotoSessions.has(sessionId)
            ? sessionId
            : undefined;
        component = page.component;
        version = page.version;
        backgroundUrl = (page.props.photo as CommitPhoto | undefined)
            ?.backgroundUrl;
    });
    const stopStart = router.on('start', ({ detail: { visit } }) => {
        if (!visit.async) {
            pending = {
                visit,
                history: window.history.state?.commitPhotoHistory,
                url: window.location.href,
                component,
                backgroundUrl,
            };
        }
    });
    const stopResponses = (['success', 'error'] as const).map((event) =>
        router.on(event, ({ detail: { page, visitId } }) => {
            if (!visitId || visitId !== pending?.visit.id) {
                return;
            }
            const photo = page?.props.photo as CommitPhoto | undefined;
            if (
                pending.visit.preserveState !== true ||
                page?.component !== pending.component ||
                window.location.href !== pending.url ||
                window.history.state?.commitPhotoHistory?.sessionId !==
                    pending.history?.sessionId ||
                photo?.backgroundUrl !== pending.backgroundUrl
            ) {
                photoSessionId = undefined;
                if (window.history.state?.commitPhotoHistory) {
                    window.history.replaceState(
                        { ...window.history.state, commitPhotoHistory: null },
                        '',
                    );
                }
            }
            component = page?.component;
            version = page?.version;
            backgroundUrl = photo?.backgroundUrl;
        }),
    );
    const stopReload = router.on('before', ({ detail: { visit } }) => {
        if (
            !backgroundUrl ||
            visit.method !== 'get' ||
            !visit.async ||
            visit.url.origin !== window.location.origin ||
            visit.url.pathname !== window.location.pathname
        ) {
            return;
        }

        // The address bar shows the photo, but reloads still belong to the page behind it.
        const url = new URL(backgroundUrl, window.location.origin);
        for (const key of new Set(visit.url.searchParams.keys())) {
            url.searchParams.delete(key);
        }
        visit.url.searchParams.forEach((value, key) =>
            url.searchParams.append(key, value),
        );
        visit.url = url;
        visit.preserveUrl = true;
    });

    return () => {
        window.removeEventListener('popstate', onPopState, true);
        photoSessionId = undefined;
        documentPhotoSessions.clear();
        if (window.history.replaceState === preservePhotoHistory) {
            window.history.replaceState = replaceState;
        }
        stopNavigation();
        stopStart();
        stopResponses.forEach((stop) => stop());
        stopReload();
    };
}

export const commitPhotoUrl = (commit: Commit, index: number): string =>
    CommitPhotoController.show({ user: commit.user, commit, photo: index + 1 })
        .url;

export function openCommitPhoto(commit: Commit, index: number): void {
    const entry: PhotoHistory = {
        sessionId: photoSessionId ?? crypto.randomUUID(),
        entryId: crypto.randomUUID(),
    };
    photoSessionId = entry.sessionId;
    documentPhotoSessions.add(entry.sessionId);
    router.push({
        url: commitPhotoUrl(commit, index),
        props: (props) => {
            const source = window.history.state?.commitPhotoHistory as
                | PhotoHistory
                | undefined;
            window.history.replaceState(
                {
                    ...window.history.state,
                    commitPhotoHistory:
                        source?.sessionId === entry.sessionId
                            ? source
                            : {
                                  sessionId: entry.sessionId,
                                  entryId: crypto.randomUUID(),
                              },
                },
                '',
            );
            return {
                ...props,
                photo: {
                    commit,
                    index,
                    backgroundUrl:
                        (props.photo as CommitPhoto | undefined)
                            ?.backgroundUrl ?? window.location.href,
                },
            };
        },
        preserveScroll: true,
        preserveState: true,
        onFinish: () => {
            photoSessionId = entry.sessionId;
            window.history.replaceState(
                { ...window.history.state, commitPhotoHistory: entry },
                '',
            );
        },
    });
}

export function showCommitPhoto(photo: CommitPhoto, index: number): void {
    router.replace({
        url: commitPhotoUrl(photo.commit, index),
        props: (props) => ({ ...props, photo: { ...photo, index } }),
        preserveScroll: true,
        preserveState: true,
    });
}

export function closeCommitPhoto(photo: CommitPhoto): void {
    if (photo.backgroundUrl) {
        window.history.back();
        return;
    }
    router.replace({
        url: CommitController.show({
            user: photo.commit.user,
            commit: photo.commit,
        }).url,
        props: (props) => ({ ...props, photo: undefined }),
        preserveScroll: true,
        preserveState: true,
    });
}
