/// <reference types="node" />
import assert from 'node:assert/strict';
import { beforeEach, test, vi } from 'vite-plus/test';

const key = 'stash:author:commit';
const image = new File(['image'], 'draft.png', { type: 'image/png' });
const bodies = new Map<string, string>();
const storedImages = new Map<string, File[]>();
const session = new Map<string, string>();

vi.stubGlobal('sessionStorage', {
    getItem: (name: string) => session.get(name) ?? null,
    setItem: (name: string, value: string) => session.set(name, value),
    removeItem: (name: string) => session.delete(name),
});

vi.doMock('../../resources/js/lib/storage', () => ({
    writeStorage: (name: string, body: string) => {
        if (body === '') {
            bodies.delete(name);
        } else {
            bodies.set(name, body);
        }
    },
}));

vi.doMock('../../resources/js/lib/image-storage', () => ({
    writeStoredImages: (name: string, images: File[]) => {
        if (images.length === 0) {
            storedImages.delete(name);
        } else {
            storedImages.set(name, images);
        }
    },
}));

const { dropPostedDraft, forgetPosting, rememberPosting } =
    await import('../../resources/js/lib/posting-draft');

beforeEach(() => {
    bodies.clear();
    storedImages.clear();
    session.clear();
    bodies.set(key, 'Posted as the page reloaded');
    storedImages.set(key, [image]);
});

test('drops the draft a reload interrupted the posting of once its commit is flashed', () => {
    rememberPosting(key);

    dropPostedDraft({ committed: { id: 'commit', body: 'Posted' } });

    assert.equal(bodies.has(key), false);
    assert.equal(storedImages.has(key), false);
    assert.equal(session.size, 0);
});

test('drops the draft once a scheduled commit is flashed', () => {
    rememberPosting(key);

    dropPostedDraft({ scheduled: true });

    assert.equal(bodies.has(key), false);
    assert.equal(storedImages.has(key), false);
});

test('keeps the draft when the reloaded page flashes no commit', () => {
    rememberPosting(key);

    dropPostedDraft({ toast: { type: 'error', message: 'Too many commits.' } });
    dropPostedDraft({ committed: { id: 'commit', body: 'Posted' } });

    assert.equal(bodies.get(key), 'Posted as the page reloaded');
    assert.deepEqual(storedImages.get(key), [image]);
});

test('keeps the draft when the reloaded page flashes nothing at all', () => {
    rememberPosting(key);

    dropPostedDraft(undefined);

    assert.equal(bodies.get(key), 'Posted as the page reloaded');
    assert.equal(session.size, 0);
});

test('forgets the draft being posted, but not on behalf of another composer', () => {
    rememberPosting(key);
    forgetPosting('stash:author:quote-commit');

    assert.equal(session.size, 1);

    forgetPosting(key);
    dropPostedDraft({ committed: { id: 'commit', body: 'Posted' } });

    assert.equal(bodies.has(key), true);
});
