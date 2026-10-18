/// <reference types="node" />
import assert from 'node:assert/strict';
import * as React from 'react';
import { test, vi } from 'vite-plus/test';

const bodies = new Map<string, string>([['stash:author:commit', 'First draft']]);
const image = new File(['image'], 'draft.png', { type: 'image/png' });
const storedImages = new Map<
    string,
    { images: File[]; descriptions: string[] }
>([
    [
        'stash:author:commit',
        { images: [image], descriptions: ['A saved screenshot'] },
    ],
    [
        'stash:author:reply-parent',
        { images: [image], descriptions: ['A saved screenshot'] },
    ],
    [
        'stash:another:commit',
        { images: [image], descriptions: ['A saved screenshot'] },
    ],
]);

let state: unknown[] = [];
let cursor = 0;
let cleanups: (() => void)[] = [];

vi.doMock('react', () => ({
    ...React,
    useEffect: (effect: () => (() => void) | void) => {
        const cleanup = effect();

        if (cleanup) {
            cleanups.push(cleanup);
        }
    },
    useState: (initial: unknown) => {
        const index = cursor++;

        if (!(index in state)) {
            state[index] = typeof initial === 'function' ? initial() : initial;
        }

        const current = state;

        return [state[index], (value: unknown) => (current[index] = value)];
    },
    useSyncExternalStore: (
        subscribe: (callback: () => void) => () => void,
        snapshot: () => unknown,
    ) => {
        const unsubscribe = subscribe(() => {});
        unsubscribe();

        return snapshot();
    },
}));

vi.doMock('../../resources/js/lib/storage', () => ({
    readStorage: (key: string) => bodies.get(key) ?? '',
    writeStorage: (key: string, body: string) => {
        if (body === '') {
            bodies.delete(key);
        } else {
            bodies.set(key, body);
        }
    },
    storedKeys: () => [...bodies.keys()],
}));

vi.doMock('../../resources/js/lib/image-storage', () => ({
    readStoredImages: async () => new Map(storedImages),
    writeStoredImages: (
        key: string,
        images: File[],
        descriptions: string[],
    ) => {
        if (images.length === 0) {
            storedImages.delete(key);
        } else {
            storedImages.set(key, { images, descriptions });
        }
    },
    deleteStoredImages: (prefix: string) => {
        for (const key of storedImages.keys()) {
            if (key.startsWith(prefix)) {
                storedImages.delete(key);
            }
        }
    },
}));

const { clearStashes, useDraftPart, useHasStash, useStash } =
    await import('../../resources/js/hooks/use-stash');

test('restores draft images, preserves them through body edits, and drops the complete draft', async () => {
    const windowBefore = globalThis.window;
    globalThis.window = { addEventListener() {} } as unknown as Window &
        typeof globalThis;

    const render = () => {
        cursor = 0;

        return useStash('author', 'commit');
    };

    try {
        render();
        await Promise.resolve();

        let draft = render();
        assert.equal(draft.body, 'First draft');
        assert.deepEqual(draft.images, [image]);
        assert.deepEqual(draft.descriptions, ['A saved screenshot']);
        assert.equal(draft.isRestored, true);

        draft.updateBody('Edited draft');
        draft = render();
        assert.equal(draft.body, 'Edited draft');
        assert.deepEqual(draft.images, [image]);

        state = [];
        draft = render();
        assert.equal(draft.body, 'Edited draft');
        assert.deepEqual(draft.images, [image]);

        draft.updateBody('');
        draft = render();
        assert.equal(draft.isRestored, true);

        draft.updateImages([]);
        draft = render();
        assert.equal(draft.isRestored, false);
        assert.deepEqual(draft.images, []);

        draft.updateImages([image]);
        assert.deepEqual(storedImages.get('stash:author:commit'), {
            images: [image],
            descriptions: [''],
        });
        draft = render();
        draft.drop();
        draft = render();

        assert.equal(draft.body, '');
        assert.deepEqual(draft.images, []);
        assert.equal(draft.isRestored, false);
        assert.equal(storedImages.has('stash:author:commit'), false);
        assert.equal(useHasStash('author', 'commit'), false);

        state = [];
        cursor = 0;
        const reply = useStash('author', 'reply-parent');
        assert.deepEqual(reply.images, [image]);
        assert.equal(reply.isRestored, true);
        assert.equal(useHasStash('author', 'reply-parent'), true);
        assert.equal(useHasStash('another', 'commit'), true);
        assert.equal(useHasStash(null, 'reply-parent'), false);

        clearStashes('author');
        assert.equal(useHasStash('author', 'reply-parent'), false);
        assert.equal(storedImages.has('stash:author:reply-parent'), false);
        assert.equal(useHasStash('another', 'commit'), true);
    } finally {
        clearStashes('author');
        clearStashes('another');
        globalThis.window = windowBefore;
    }
});

test('drafts keep sensitive text in the editor and images in storage without storing secrets', () => {
        const token = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';
        const name = 'sensitive';
        const key = `stash:author:${name}`;
        bodies.set(key, token);
        state = [];
        const render = () => {
            cursor = 0;
            return useStash('author', name);
        };
        vi.stubGlobal('window', { addEventListener() {} });
        try {
            let draft = render();
            assert.equal(draft.body, token);
            assert.equal(bodies.has(key), false);

            draft.updateImages([image]);
            draft = render();
            draft.updateBody('Ordinary edited body');
            draft = render();
            draft.updateBody(token);
            draft = render();

            assert.equal(draft.body, token);
            assert.deepEqual(draft.images, [image]);
            assert.deepEqual(storedImages.get(key), {
                images: [image],
                descriptions: [''],
            });
            assert.equal(bodies.has(key), false);
            assert.equal(useHasStash('author', name), true);

            draft.drop();
            draft = render();
            assert.equal(draft.body, '');
            assert.deepEqual(draft.images, []);
            assert.equal(storedImages.has(key), false);
            assert.equal(useHasStash('author', name), false);
        } finally {
            clearStashes('author');
            vi.unstubAllGlobals();
        }
});

test('drafts keep descriptions paired with repeated files through filtering, removal, restoration and clearing', async () => {
        vi.resetModules();
        const name = 'described';
        const key = `stash:described:${name}`;
        const invalid = new File(['text'], 'invalid.txt', {
            type: 'text/plain',
        });
        const second = image;
        storedImages.set(key, {
            images: [invalid, image, second],
            descriptions: [
                'Invalid file',
                'First screenshot',
                'Second screenshot',
            ],
        });
        const hooks = await import('../../resources/js/hooks/use-stash');
        const { useImageAttachments } =
            await import('../../resources/js/hooks/use-image-attachments');
        state = [];
        vi.stubGlobal('window', { addEventListener() {} });
        const render = () => {
            cursor = 0;
            const draft = hooks.useStash('described', name);
            const attachments = useImageAttachments(
                draft.images,
                draft.descriptions,
                draft.updateImages,
            );
            return { draft, attachments };
        };
        try {
            render();
            await Promise.resolve();
            let view = render();
            assert.deepEqual(view.draft.images, [image, second]);
            assert.deepEqual(view.draft.descriptions, [
                'First screenshot',
                'Second screenshot',
            ]);
            view.attachments.describe(1, 'Edited second screenshot');
            view = render();
            view.attachments.remove(0);
            view = render();
            assert.deepEqual(view.draft.images, [second]);
            assert.deepEqual(view.draft.descriptions, [
                'Edited second screenshot',
            ]);
            assert.deepEqual(storedImages.get(key), {
                images: [second],
                descriptions: ['Edited second screenshot'],
            });
            vi.resetModules();
            const restoredHooks =
                await import('../../resources/js/hooks/use-stash');
            state = [];
            const restore = () => {
                cursor = 0;
                return restoredHooks.useStash('described', name);
            };
            restore();
            await Promise.resolve();
            let restored = restore();
            assert.deepEqual(restored.images, [second]);
            assert.deepEqual(restored.descriptions, [
                'Edited second screenshot',
            ]);
            assert.equal(restored.isRestored, true);
            restored.drop();
            restored = restore();
            assert.deepEqual(restored.images, []);
            assert.deepEqual(restored.descriptions, []);
            assert.equal(storedImages.has(key), false);
        } finally {
            hooks.clearStashes('described');
            vi.unstubAllGlobals();
        }
});

test('drafts release image URLs on replacement, drop and logout while keeping retained images usable', async () => {
        vi.resetModules();
        const hooks = await import('../../resources/js/hooks/use-stash');
        const { objectUrlFor } =
            await import('../../resources/js/components/commit-image-picker');
        const first = new File(['first'], 'first.png', { type: 'image/png' });
        const second = new File(['second'], 'second.png', {
            type: 'image/png',
        });
        const revoke = vi.spyOn(URL, 'revokeObjectURL');
        state = [];
        const render = () => {
            cursor = 0;
            return hooks.useStash('url-owner', 'commit');
        };
        try {
            let draft = render();
            draft.updateImages([first, first]);
            const firstUrl = objectUrlFor(first);
            draft = render();
            draft.updateImages([first]);
            assert.equal(revoke.mock.calls.length, 0);
            assert.equal(objectUrlFor(first), firstUrl);
            draft = render();
            draft.updateImages([second]);
            assert.deepEqual(revoke.mock.calls, [[firstUrl]]);
            const secondUrl = objectUrlFor(second);
            draft = render();
            draft.drop();
            assert.deepEqual(revoke.mock.calls, [[firstUrl], [secondUrl]]);
            draft = render();
            draft.updateImages([first]);
            const restoredUrl = objectUrlFor(first);
            assert.notEqual(restoredUrl, firstUrl);
            hooks.clearStashes('url-owner');
            assert.deepEqual(revoke.mock.lastCall, [restoredUrl]);
            assert.equal(hooks.useHasStash('url-owner', 'commit'), false);
        } finally {
            hooks.clearStashes('url-owner');
            revoke.mockRestore();
        }
});

test('the log composer and the dialog share one live draft, so closing the dialog needs no remount', () => {
    const token = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';
    const key = 'stash:author:shared';
    const composer = () => {
        let own: unknown[] = [];

        return () => {
            state = own;
            cursor = 0;
            const draft = useStash('author', 'shared');
            own = state;

            return draft;
        };
    };
    const log = composer();
    const dialog = composer();
    vi.stubGlobal('window', { addEventListener() {} });

    try {
        log().updateBody(token);
        assert.equal(dialog().body, token);
        assert.equal(bodies.has(key), false);

        dialog().updateBody('Typed in the dialog');
        assert.equal(log().body, 'Typed in the dialog');

        assert.equal(log().dropCount, 0);
        dialog().drop();
        assert.equal(log().body, '');
        assert.equal(log().dropCount, 1);

        clearStashes('author');
        assert.equal(log().body, '');
        assert.equal(dialog().body, '');
    } finally {
        clearStashes('author');
        vi.unstubAllGlobals();
    }
});

test('a draft is read from storage again once no composer shows it, so another tab can change it', () => {
    const key = 'stash:author:tabs';
    bodies.set(key, 'Written here');
    state = [];
    cleanups = [];
    cursor = 0;

    try {
        assert.equal(useStash('author', 'tabs').body, 'Written here');
        cleanups.forEach((cleanup) => cleanup());
        bodies.set(key, 'Changed in another tab');

        state = [];
        cursor = 0;
        assert.equal(useStash('author', 'tabs').body, 'Changed in another tab');
    } finally {
        clearStashes('author');
    }
});

test('the log composer and the dialog share the rest of the draft, and a drop resets it for both', () => {
    const poll = { options: ['Tabs', 'Spaces'], duration: 60 };
    state = [];
    cursor = 0;
    vi.stubGlobal('window', { addEventListener() {} });

    try {
        useStash('author', 'commit');
        useDraftPart('author', 'commit', 'poll')[1](poll);
        useDraftPart('author', 'commit', 'issueKind')[1]('bug');
        useDraftPart('author', 'commit', 'isScheduling')[1](true);
        useDraftPart('author', 'commit', 'linkCard')[1](null);

        assert.deepEqual(useDraftPart('author', 'commit', 'poll')[0], poll);
        assert.equal(useDraftPart('author', 'commit', 'issueKind')[0], 'bug');
        assert.equal(useDraftPart('author', 'commit', 'isScheduling')[0], true);
        assert.equal(useDraftPart('author', 'commit', 'linkCard')[0], null);
        assert.equal(useDraftPart('another', 'commit', 'poll')[0], null);

        useStash('author', 'commit').drop();

        assert.equal(useDraftPart('author', 'commit', 'poll')[0], null);
        assert.equal(useDraftPart('author', 'commit', 'issueKind')[0], null);
        assert.equal(useDraftPart('author', 'commit', 'isScheduling')[0], false);
        assert.equal(useDraftPart('author', 'commit', 'linkCard')[0], undefined);

        useDraftPart('author', 'commit', 'poll')[1](poll);
        clearStashes('author');
        assert.equal(useDraftPart('author', 'commit', 'poll')[0], null);
    } finally {
        clearStashes('author');
        vi.unstubAllGlobals();
    }
});

test('the log composer and the dialog agree on whether the draft is restored', () => {
    const key = 'stash:author:restored';
    const composer = () => {
        let own: unknown[] = [];

        return () => {
            state = own;
            cursor = 0;
            const draft = useStash('author', 'restored');
            own = state;

            return draft;
        };
    };
    const log = composer();
    const dialog = composer();
    bodies.set(key, 'Saved before reload');
    vi.stubGlobal('window', { addEventListener() {} });

    try {
        assert.equal(log().isRestored, true);
        assert.equal(dialog().isRestored, true);

        dialog().updateBody('');
        assert.equal(log().isRestored, false);

        log().updateBody('Typed after the page loaded');
        assert.equal(log().isRestored, false);
        assert.equal(dialog().isRestored, false);
    } finally {
        clearStashes('author');
        vi.unstubAllGlobals();
    }
});

test('a composer that finishes after it unmounts leaves nothing in memory, so storage is read again', () => {
    const key = 'stash:author:late';
    bodies.set(key, 'Written here');
    state = [];
    cleanups = [];
    cursor = 0;
    vi.stubGlobal('window', { addEventListener() {} });

    try {
        const draft = useStash('author', 'late');
        const [, setKind] = useDraftPart('author', 'late', 'issueKind');
        cleanups.forEach((cleanup) => cleanup());

        setKind('bug');
        draft.drop();
        bodies.set(key, 'Changed in another tab');

        state = [];
        cursor = 0;
        const remounted = useStash('author', 'late');
        assert.equal(remounted.body, 'Changed in another tab');
        assert.equal(remounted.dropCount, 0);
        assert.equal(useDraftPart('author', 'late', 'issueKind')[0], null);
    } finally {
        clearStashes('author');
        vi.unstubAllGlobals();
    }
});

test('a stored draft with a secret stays in the editor while it leaves storage', () => {
    const token = 'ghp_' + 'Ab3dEf6hIj9kLm2nOp5qRs8tUv1wXy4zABCD';
    const key = 'stash:author:stored-secret';
    bodies.set(key, token);
    state = [];
    cleanups = [];
    cursor = 0;
    vi.stubGlobal('window', { addEventListener() {} });

    try {
        useStash('author', 'stored-secret');
        assert.equal(bodies.has(key), false);

        state = [];
        cursor = 0;
        const draft = useStash('author', 'stored-secret');
        assert.equal(draft.body, token);
        assert.equal(draft.isRestored, true);
    } finally {
        cleanups.forEach((cleanup) => cleanup());
        clearStashes('author');
        vi.unstubAllGlobals();
    }
});
