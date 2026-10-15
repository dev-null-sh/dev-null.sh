import { router } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import CommitPhotoController from '@/actions/App/Http/Controllers/CommitPhotoController';
import { fetchReplies } from '@/lib/replies';
import type { Commit, CursorPaginated, ThreadCommit } from '@/types';

export type CommitPhoto = {
    commit: Commit;
    index: number;
};

export type CommitPhotoReplies = Promise<
    CursorPaginated<ThreadCommit> | undefined
>;

const replies = new Map<string, CommitPhotoReplies>();
let isOpenedInApp = false;

export const commitPhotoUrl = (commit: Commit, index: number): string =>
    CommitPhotoController.show({
        user: commit.user,
        commit,
        photo: index + 1,
    }).url;

export function openCommitPhoto(commit: Commit, index: number): void {
    isOpenedInApp = true;
    replies.clear();

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

export function repliesFor(commit: Commit): CommitPhotoReplies {
    const cached = replies.get(commit.id);

    if (cached) {
        return cached;
    }

    const loaded = commit.replies_count
        ? fetchReplies(commit, null).catch(() => undefined)
        : Promise.resolve(undefined);

    replies.set(commit.id, loaded);

    return loaded;
}
