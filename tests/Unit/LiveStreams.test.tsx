import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import {
    currentLiveStream,
    watchLiveStream,
} from '../../resources/js/lib/live-streams';
import { connectRealtime, pageReadAt } from '../../resources/js/lib/realtime';
import type { LiveStream } from '../../resources/js/types/auth';
import { fakeConnection } from './FakeRealtime';

const stream = (url: string): LiveStream => ({
    url,
    thumbnail_url: 'thumbnail',
    ends_at: new Date(Date.now() + 3_600_000).toISOString(),
});
const reads: string[][] = [];
let current: Record<string, LiveStream | null> = {};

afterEach(() => {
    vi.unstubAllGlobals();
    connectRealtime(null);
    reads.length = 0;
    current = {};
});

function connect() {
    vi.stubGlobal('fetch', async (input: string) => {
        const ids = new URL(input, 'http://localhost').searchParams.getAll(
            'ids[]',
        );
        reads.push(ids);

        return Response.json({
            streams: Object.fromEntries(
                ids.map((id) => [id, current[id] ?? null]),
            ),
        });
    });
    const fake = fakeConnection();
    connectRealtime(fake.connection);
    fake.setStatus('connected');

    return fake;
}

const settle = () => new Promise((resolve) => setTimeout(resolve, 80));

test('a stream heard before the page was read gives way to what the page read', async () => {
    const fake = connect();
    const stop = watchLiveStream('ended');
    fake.joined.get('profiles.ended')?.join();
    await settle();
    current = { ended: stream('https://www.twitch.tv/old') };
    fake.joined.get('profiles.ended')?.emit('profile', { live: true });
    await settle();
    await new Promise((resolve) => setTimeout(resolve, 5));
    pageReadAt(Date.now());

    assert.equal(currentLiveStream('ended', null, new Date()), null);
    stop();
});

test('a stream heard after the page was read is shown over what the page read', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const stop = watchLiveStream('started');
    fake.joined.get('profiles.started')?.join();
    await settle();
    current = { started: stream('https://www.twitch.tv/new') };
    fake.joined.get('profiles.started')?.emit('profile', { live: true });
    await settle();

    assert.equal(
        currentLiveStream('started', null, new Date())?.url,
        'https://www.twitch.tv/new',
    );
    stop();
});

test('listening again reads whether the people shown are live, in one request, so a missed start or end shows', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const stops = [watchLiveStream('went-live'), watchLiveStream('stopped')];
    current = {
        'went-live': stream('https://www.twitch.tv/went-live'),
        stopped: null,
    };
    fake.joined.get('profiles.went-live')?.join();
    fake.joined.get('profiles.stopped')?.join();
    await settle();

    assert.deepEqual(
        reads.map((ids) => ids.sort()),
        [['stopped', 'went-live']],
    );
    assert.equal(
        currentLiveStream('went-live', null, new Date())?.url,
        'https://www.twitch.tv/went-live',
    );
    assert.equal(
        currentLiveStream(
            'stopped',
            stream('https://www.twitch.tv/stopped'),
            new Date(),
        ),
        null,
    );
    stops.forEach((stop) => stop());
});

test('public stream events cannot override a block-filtered read', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const stop = watchLiveStream('blocked');
    fake.joined.get('profiles.blocked')?.join();
    await settle();
    const before = reads.length;
    fake.joined
        .get('profiles.blocked')
        ?.emit('profile', { live: stream('https://twitch.tv/blocked') });
    await settle();

    assert.equal(currentLiveStream('blocked', null, new Date()), null);
    assert.equal(reads.length, before + 1);
    stop();
});

test('a connection change clears an already-heard stream through a fresh authorized read', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const live = stream('https://www.twitch.tv/relationship');
    current = { relationship: live };
    const stop = watchLiveStream('relationship');
    fake.joined.get('profiles.relationship')?.join();
    await settle();
    assert.equal(
        currentLiveStream('relationship', null, new Date())?.url,
        live.url,
    );
    const before = reads.length;
    current = { relationship: null };
    fake.joined
        .get('profiles.relationship')
        ?.emit('profile', { connections: true });
    await settle();
    assert.equal(reads.length, before + 1);
    assert.equal(currentLiveStream('relationship', live, new Date()), null);
    stop();
});

test('connection changes coalesce authorized reads for all affected shown profiles', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const ids = ['relationship-a', 'relationship-b'];
    const stops = ids.map(watchLiveStream);
    ids.forEach((id) => fake.joined.get(`profiles.${id}`)?.join());
    await settle();
    const before = reads.length;
    current = Object.fromEntries(
        ids.map((id) => [id, stream(`https://www.twitch.tv/${id}`)]),
    );
    ids.forEach((id) => {
        fake.joined
            .get(`profiles.${id}`)
            ?.emit('profile', { connections: true });
        fake.joined
            .get(`profiles.${id}`)
            ?.emit('profile', { connections: true });
    });
    await settle();
    assert.equal(reads.length, before + 1);
    assert.deepEqual([...reads.at(-1)!].sort(), ids);
    ids.forEach((id) =>
        assert.equal(
            currentLiveStream(id, null, new Date())?.url,
            current[id]?.url,
        ),
    );
    stops.forEach((stop) => stop());
});
