import { router } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitPhotoController from '@/actions/App/Http/Controllers/CommitPhotoController';
import type { Commit } from '@/types';

export type CommitPhoto = {
    commit: Commit;
    index: number;
    backgroundUrl?: string;
};

let isOpenedInApp = false;

export function trackCommitPhotoPage(): VoidFunction {
    let backgroundUrl: string | undefined;

    const stopNavigation = router.on('navigate', ({ detail: { page } }) => {
        backgroundUrl = (page.props.photo as CommitPhoto | undefined)
            ?.backgroundUrl;
    });
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
        stopNavigation();
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
    isOpenedInApp = true;
    const backgroundUrl = window.location.href;

    router.push({
        url: commitPhotoUrl(commit, index),
        props: (props) => ({
            ...props,
            photo: {
                commit,
                index,
                backgroundUrl:
                    (props.photo as CommitPhoto | undefined)?.backgroundUrl ??
                    backgroundUrl,
            },
        }),
        preserveScroll: true,
        preserveState: true,
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
    if (isOpenedInApp) {
        isOpenedInApp = false;
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
