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

const photoPages = new Map<string, CommitPhoto | undefined>();

export function trackCommitPhotoPage(): VoidFunction {
    let backgroundUrl: string | undefined;
    let pendingVisit: PendingVisit | undefined;

    const onPopState = (event: PopStateEvent) => {
        const url = window.location.href;
        if (!photoPages.has(url)) {
            return;
        }

        // Inertia's history restoration remounts the page, losing replies and measured offscreen card heights.
        event.stopImmediatePropagation();
        router.cancelAll({ prefetch: false });
        const photo = photoPages.get(url);
        router.replace({
            url,
            props: (props) => ({ ...props, photo }),
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                backgroundUrl = photo?.backgroundUrl;
            },
        });
    };
    window.addEventListener('popstate', onPopState, true);

    const stopNavigation = router.on('navigate', ({ detail: { page } }) => {
        if (!photoPages.has(window.location.href)) {
            photoPages.clear();
        }
        backgroundUrl = (page.props.photo as CommitPhoto | undefined)
            ?.backgroundUrl;
    });
    const stopStart = router.on('start', ({ detail: { visit } }) => {
        if (!visit.async) {
            pendingVisit = visit;
        }
    });
    const stopResponses = (['success', 'error'] as const).map((event) =>
        router.on(event, ({ detail: { page, visitId } }) => {
            if (!visitId || visitId !== pendingVisit?.id) {
                return;
            }

            const photo = page?.props.photo as CommitPhoto | undefined;
            if (
                pendingVisit.preserveState !== true ||
                !photoPages.has(window.location.href) ||
                photo?.backgroundUrl !==
                    photoPages.get(window.location.href)?.backgroundUrl
            ) {
                photoPages.clear();
            }
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
        photoPages.clear();
        stopNavigation();
        stopStart();
        stopResponses.forEach((stop) => stop());
        stopReload();
    };
}

export const commitPhotoUrl = (commit: Commit, index: number): string =>
    CommitPhotoController.show({
        user: commit.user,
        commit,
        photo: index + 1,
    }).url;

export function openCommitPhoto(commit: Commit, index: number): void {
    const backgroundUrl = window.location.href;

    router.push({
        url: commitPhotoUrl(commit, index),
        props: (props) => {
            const photo = {
                commit,
                index,
                backgroundUrl:
                    (props.photo as CommitPhoto | undefined)?.backgroundUrl ??
                    backgroundUrl,
            };
            if (!photoPages.has(photo.backgroundUrl)) {
                photoPages.set(
                    photo.backgroundUrl,
                    props.photo as CommitPhoto | undefined,
                );
            }
            photoPages.set(
                new URL(commitPhotoUrl(commit, index), backgroundUrl).href,
                photo,
            );

            return { ...props, photo };
        },
        preserveScroll: true,
        preserveState: true,
    });
}

export function showCommitPhoto(photo: CommitPhoto, index: number): void {
    if (photo.backgroundUrl || photoPages.has(window.location.href)) {
        photoPages.set(
            new URL(commitPhotoUrl(photo.commit, index), window.location.href)
                .href,
            { ...photo, index },
        );
    }
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

    const url = CommitController.show({
        user: photo.commit.user,
        commit: photo.commit,
    }).url;
    if (photoPages.has(window.location.href)) {
        photoPages.set(new URL(url, window.location.href).href, undefined);
    }
    router.replace({
        url,
        props: (props) => ({ ...props, photo: undefined }),
        preserveScroll: true,
        preserveState: true,
    });
}
