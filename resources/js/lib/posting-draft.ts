import type { FlashData } from '@inertiajs/core';
import { writeStoredImages } from '@/lib/image-storage';
import { writeStorage } from '@/lib/storage';

const postingKey = 'stash:posting';

/**
 * A commit posted while /dev/null is updated reloads the page rather than finishing, so its draft is never dropped.
 * The commit composer drops it once the reloaded page flashes the same body, but a quote's composer is closed by then,
 * and a scheduled commit is flashed without its body, so those drafts are remembered for the reloaded page to drop.
 */
export function rememberPosting(key: string): void {
    try {
        sessionStorage.setItem(postingKey, key);
    } catch {
        return;
    }
}

/**
 * A post that comes back, committed or not, never reloaded the page.
 */
export function forgetPosting(key: string): void {
    try {
        if (sessionStorage.getItem(postingKey) === key) {
            sessionStorage.removeItem(postingKey);
        }
    } catch {
        return;
    }
}

/**
 * Runs before the page renders, as the composers only read their draft once. A page loaded with nothing flashed
 * comes without any flash data.
 */
export function dropPostedDraft(flash: FlashData | undefined): void {
    let key: string | null;

    try {
        key = sessionStorage.getItem(postingKey);
        sessionStorage.removeItem(postingKey);
    } catch {
        return;
    }

    if (
        key === null ||
        (flash?.committed === undefined && flash?.scheduled !== true)
    ) {
        return;
    }

    writeStorage(key, '');
    writeStoredImages(key, [], []);
}
