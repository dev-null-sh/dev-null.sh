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
            onTrackDidChange = vi.fn();
            onClose = vi.fn();
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
    link: 'https://audius.co/broeybeats/paradise',
};

type Loaded = { url: string } | null;

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
    const { openDevamp } = await import('../../resources/js/lib/devamp');

    await Promise.all([openDevamp(), openDevamp()]);
    await openDevamp();

    assert.equal(instances.length, 1);
    assert.equal(appended.length, 1);
    assert.equal(appended[0].dataset.devamp, '');
    assert.equal(instances[0].reopen.mock.calls.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 0);
    assert.deepEqual(
        (options[0] as { initialTracks: unknown[]; zIndex: number }).zIndex,
        40,
    );
    assert.equal(
        (options[0] as { initialTracks: unknown[] }).initialTracks.length,
        23,
    );
});

test('plays a playlist in the open player', async () => {
    const { openDevamp } = await import('../../resources/js/lib/devamp');

    await openDevamp([track]);
    await openDevamp([track, track]);

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

test('starts a playlist before returning once Devamp is preloaded', async () => {
    const { openDevamp, preloadDevamp } = await import('../../resources/js/lib/devamp');

    preloadDevamp();
    preloadDevamp();
    await vi.dynamicImportSettled();

    const opened = openDevamp([track]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
    assert.equal((options[0] as { initialTracks: unknown }).initialTracks, undefined);

    await opened;
    preloadDevamp();
    await openDevamp();

    assert.equal(instances.length, 1);
    assert.equal(instances[0].reopen.mock.calls.length, 1);
    assert.equal(error.mock.calls.length, 0);
});

test('plays a playlist once the player that is already opening is ready', async () => {
    const { openDevamp } = await import('../../resources/js/lib/devamp');

    await Promise.all([openDevamp(), openDevamp([track])]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
});

test('lets a search open join a playlist that is already opening', async () => {
    const { openDevamp } = await import('../../resources/js/lib/devamp');

    await Promise.all([openDevamp([track]), openDevamp()]);

    assert.equal(instances.length, 1);
    assert.equal((options[0] as { initialTracks: unknown }).initialTracks, undefined);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
});

test('loads Devamp again when preloading it failed', async () => {
    const { openDevamp, preloadDevamp } = await import('../../resources/js/lib/devamp');
    failImport = true;
    vi.doMock('webamp', webamp);

    preloadDevamp();
    await vi.dynamicImportSettled();
    failImport = false;
    vi.doMock('webamp', webamp);
    await openDevamp([track]);

    assert.equal(instances.length, 1);
    assert.equal(instances[0].setTracksToPlay.mock.calls.length, 1);
    assert.equal(error.mock.calls.length, 0);
});

test('says so and leaves nothing behind when the player fails to open, then tries again', async () => {
    const { openDevamp } = await import('../../resources/js/lib/devamp');
    failRender = true;

    await openDevamp([track]);

    assert.deepEqual(error.mock.calls, [['Could not open Devamp. Please try again.']]);
    assert.equal(appended.length, 0);
    assert.equal(instances[0].dispose.mock.calls.length, 1);

    failRender = false;
    await openDevamp([track]);

    assert.equal(instances.length, 2);
    assert.equal(appended.length, 1);
    assert.equal(instances[1].setTracksToPlay.mock.calls.length, 1);
});

test('says so in browsers Devamp does not play in', async () => {
    const { openDevamp } = await import('../../resources/js/lib/devamp');
    supported = false;

    await openDevamp();

    assert.equal(instances.length, 0);
    assert.deepEqual(error.mock.calls, [["Devamp doesn't play in this browser."]]);
});

test('credits the loaded Audius track while the player is open, through pauses', async () => {
    const { devampTrack, openDevamp, subscribeToDevamp } = await import('../../resources/js/lib/devamp');
    const next = {
        ...track,
        title: 'Pillow Thoughts',
        url: 'https://api.audius.co/v1/tracks/Pw3nK/stream?app_name=devnull',
        link: 'https://audius.co/broeybeats/pillow-thoughts',
    };
    const listener = vi.fn();
    const unsubscribe = subscribeToDevamp(listener);

    await openDevamp([track, next]);
    const trackDidChange = instances[0].onTrackDidChange.mock.calls[0][0] as (loaded: Loaded) => void;
    const close = instances[0].onClose.mock.calls[0][0] as () => void;

    assert.deepEqual(devampTrack(), track);

    trackDidChange({ url: next.url });

    assert.deepEqual(devampTrack(), next);

    trackDidChange(null);

    assert.deepEqual(devampTrack(), next);

    close();

    assert.equal(devampTrack(), null);

    await openDevamp();

    assert.deepEqual(devampTrack(), next);

    trackDidChange({ url: 'blob:https://dev-null.sh/dropped-file' });

    assert.equal(devampTrack(), null);

    unsubscribe();
    close();

    assert.equal(listener.mock.calls.length, 6);
});

test('credits the first default track until another plays, and a playlist as soon as it is handed over', async () => {
    const { devampTrack, openDevamp } = await import('../../resources/js/lib/devamp');

    await openDevamp();

    assert.equal(devampTrack()?.link, 'https://audius.co/stripess/austin-millz-ride-stripess-flip');

    (instances[0].onTrackDidChange.mock.calls[0][0] as (loaded: Loaded) => void)({
        url: (options[0] as { initialTracks: Array<{ url: string }> }).initialTracks[1].url,
    });

    assert.equal(devampTrack()?.link, 'https://audius.co/EmmitFenn/into-the-deep');

    await openDevamp([track]);

    assert.deepEqual(devampTrack(), track);
});
