import assert from 'node:assert/strict';
import { afterEach, test, vi } from 'vite-plus/test';
import { currentLiveStream, watchLiveStream } from '../../resources/js/lib/live-streams';
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
        const ids = new URL(input, 'http://localhost').searchParams.getAll('ids[]');
        reads.push(ids);

        return Response.json({ streams: Object.fromEntries(ids.map((id) => [id, current[id] ?? null])) });
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
    fake.joined.get('profiles.ended')?.emit('profile', { live: stream('https://www.twitch.tv/old') });
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
    fake.joined.get('profiles.started')?.emit('profile', { live: stream('https://www.twitch.tv/new') });

    assert.equal(currentLiveStream('started', null, new Date())?.url, 'https://www.twitch.tv/new');
    stop();
});

test('listening again reads whether the people shown are live, in one request, so a missed start or end shows', async () => {
    const fake = connect();
    pageReadAt(Date.now() - 1_000);
    const stops = [watchLiveStream('went-live'), watchLiveStream('stopped')];
    current = { 'went-live': stream('https://www.twitch.tv/went-live'), stopped: null };
    fake.joined.get('profiles.went-live')?.join();
    fake.joined.get('profiles.stopped')?.join();
    await settle();

    assert.deepEqual(reads.map((ids) => ids.sort()), [['stopped', 'went-live']]);
    assert.equal(currentLiveStream('went-live', null, new Date())?.url, 'https://www.twitch.tv/went-live');
    assert.equal(currentLiveStream('stopped', stream('https://www.twitch.tv/stopped'), new Date()), null);
    stops.forEach((stop) => stop());
});

test('streams a page was handed without when they run out are read once, together, and stop showing when they do', async () => {
    reads.length = 0;
    vi.stubGlobal('fetch', async (input: string) => {
        const ids = new URL(input, 'http://localhost').searchParams.getAll('ids[]');
        reads.push(ids);

        return Response.json({ streams: Object.fromEntries(ids.map((id) => [id, current[id] ?? null])) });
    });
    const handed = { url: 'https://www.twitch.tv/handed', thumbnail_url: 'thumbnail' } as LiveStream;
    const endsAt = Date.now() + 60_000;
    current = {
        first: { ...handed, ends_at: new Date(endsAt).toISOString() },
        second: { ...handed, ends_at: new Date(endsAt).toISOString() },
    };
    pageReadAt(Date.now() - 1_000);

    const stops = [watchLiveStream('first', true), watchLiveStream('second', true), watchLiveStream('known', false)];
    await settle();

    assert.deepEqual(reads, [['first', 'second']], 'one read, only for streams handed without when they end');
    assert.equal(currentLiveStream('first', handed, new Date(endsAt - 1_000))?.url, 'https://www.twitch.tv/handed');
    assert.equal(currentLiveStream('first', handed, new Date(endsAt + 1_000)), null);
    stops.forEach((stop) => stop());
});
