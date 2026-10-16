import { router } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitPhotoController from '@/actions/App/Http/Controllers/CommitPhotoController';
import type { Commit } from '@/types';

export type CommitPhoto = {
    commit: Commit;
    index: number;
};

let isOpenedInApp = false;

export const commitPhotoUrl = (commit: Commit, index: number): string =>
    CommitPhotoController.show({
        user: commit.user,
        commit,
        photo: index + 1,
    }).url;

export function openCommitPhoto(commit: Commit, index: number): void {
    isOpenedInApp = true;

    router.push({
        url: commitPhotoUrl(commit, index),
        props: (props) => ({ ...props, photo: { commit, index } }),
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
