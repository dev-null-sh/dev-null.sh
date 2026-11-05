/// <reference types="node" />
import assert from 'node:assert/strict';
import { beforeEach, test, vi } from 'vite-plus/test';

const instances: Array<Record<string, ReturnType<typeof vi.fn>>> = [];
const options: unknown[] = [];
const appended: Array<{ dataset: Record<string, string>; remove: () => void }> = [];
const error = vi.fn();
let supported = true;
let failRender = false;
let failImport = false;

vi.doMock('sonner', () => ({ toast: { error } }));
const webamp = () => {
    if (failImport) {
        throw new Error('chunk');
    }

    return {
        default: class {
            static browserIsSupported = () => supported;

            reopen = vi.fn();
            setTracksToPlay = vi.fn();
            dispose = vi.fn();
            renderInto = vi.fn(() =>
                failRender ? Promise.reject(new Error('chunk')) : Promise.resolve(),
            );

            constructor(given: unknown) {
                options.push(given);
                instances.push(this as unknown as Record<string, ReturnType<typeof vi.fn>>);
            }
        },
    };
};

vi.doMock('webamp', webamp);

Object.assign(globalThis, {
    document: {
        createElement: () => {
            const element = {
                dataset: {},
                remove: () => appended.splice(appended.indexOf(element), 1),
            };

            return element;
        },
        body: { append: (element: (typeof appended)[number]) => appended.push(element) },
    },
});

const track = {
    artist: 'Broey.',
    title: 'Paradise',
    duration: 143,
    url: 'https://api.audius.co/v1/tracks/emKAp/stream?app_name=devnull',
};

beforeEach(() => {
    vi.resetModules();
    instances.length = 0;
    options.length = 0;
    appended.length = 0;
    error.mockClear();
    supported = true;
    failRender = false;
    failImport = false;
});

test('opens one player with the default playlist and reopens it after that', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');

    await Promise.all([openWinamp(), openWinamp()]);
    await openWinamp();

    assert.equal(instances.length, 1);
    assert.equal(appended.length, 1);
    assert.equal(appended[0].dataset.winamp, '');
    assert.equal(instances[0].reopen.mock.calls.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 0);
    assert.deepEqual(
        (options[0] as { initialTracks: unknown[]; zIndex: number }).zIndex,
        40,
    );
    assert.equal(
        (options[0] as { initialTracks: unknown[] }).initialTracks.length,
        6,
    );
});

test('plays a playlist in the open player', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');

    await openWinamp([track]);
    await openWinamp([track, track]);

    assert.equal(instances.length, 1);
    assert.deepEqual(instances[0].setTracksToPlay.mock.calls, [
        [[{ metaData: { artist: 'Broey.', title: 'Paradise' }, duration: 143, url: track.url }]],
        [
            [
                { metaData: { artist: 'Broey.', title: 'Paradise' }, duration: 143, url: track.url },
                { metaData: { artist: 'Broey.', title: 'Paradise' }, duration: 143, url: track.url },
            ],
        ],
    ]);
});

test('starts a playlist before returning once Winamp is preloaded', async () => {
    const { openWinamp, preloadWinamp } = await import('../../resources/js/lib/winamp');

    preloadWinamp();
    preloadWinamp();
    await vi.dynamicImportSettled();

    const opened = openWinamp([track]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
    assert.equal((options[0] as { initialTracks: unknown }).initialTracks, undefined);

    await opened;
    preloadWinamp();
    await openWinamp();

    assert.equal(instances.length, 1);
    assert.equal(instances[0].reopen.mock.calls.length, 1);
    assert.equal(error.mock.calls.length, 0);
});

test('plays a playlist once the player that is already opening is ready', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');

    await Promise.all([openWinamp(), openWinamp([track])]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
});

test('lets a search open join a playlist that is already opening', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');

    await Promise.all([openWinamp([track]), openWinamp()]);

    assert.equal(instances.length, 1);
    assert.equal((options[0] as { initialTracks: unknown }).initialTracks, undefined);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
});

test('loads Winamp again when preloading it failed', async () => {
    const { openWinamp, preloadWinamp } = await import('../../resources/js/lib/winamp');
    failImport = true;
    vi.doMock('webamp', webamp);

    preloadWinamp();
    await vi.dynamicImportSettled();
    failImport = false;
    vi.doMock('webamp', webamp);
    await openWinamp([track]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
    assert.equal(error.mock.calls.length, 0);
});

test('says so and leaves nothing behind when the player fails to open, then tries again', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');
    failRender = true;

    await openWinamp([track]);

    assert.deepEqual(error.mock.calls, [['Could not open Winamp. Please try again.']]);
    assert.equal(appended.length, 0);
    assert.equal(instances[0].dispose.mock.calls.length, 1);

    failRender = false;
    await openWinamp([track]);

    assert.equal(instances.length, 2);
    assert.equal(appended.length, 1);
    assert.equal(instances[1].setTracksToPlay.mock.calls.length, 1);
});

test('says so in browsers Winamp does not play in', async () => {
    const { openWinamp } = await import('../../resources/js/lib/winamp');
    supported = false;

    await openWinamp();

    assert.equal(instances.length, 0);
    assert.deepEqual(error.mock.calls, [["Winamp doesn't play in this browser."]]);
});
